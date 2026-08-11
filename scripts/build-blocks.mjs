#!/usr/bin/env node
import { createHash } from 'node:crypto';
import { mkdtemp, readFile, rm, writeFile, mkdir, copyFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'esbuild';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const checkOnly = process.argv.includes('--check');
const temp = await mkdtemp(join(tmpdir(), 'wpcc-assets-'));
const outputRoot = checkOnly ? temp : root;
const blocks = {
  'conversion-status': ['react', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-i18n'],
  'language-switcher': ['react', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-data', 'wp-i18n'],
  'no-conversion': ['react', 'wp-block-editor', 'wp-blocks', 'wp-i18n'],
};
const generated = [];

async function emit(path, contents) {
  const destination = join(outputRoot, path);
  await mkdir(dirname(destination), { recursive: true });
  await writeFile(destination, contents);
  generated.push(path);
}

try {
  for (const [name, dependencies] of Object.entries(blocks)) {
    const sourceRoot = join(root, 'includes/blocks/src', name);
    const outputPath = join(outputRoot, 'includes/blocks/build', name, 'index.js');
    await mkdir(dirname(outputPath), { recursive: true });
    await build({
      entryPoints: [join(sourceRoot, 'index.js')],
      outfile: outputPath,
      bundle: true,
      format: 'iife',
      minify: true,
      legalComments: 'none',
      target: ['es2018'],
      charset: 'utf8',
      logLevel: 'silent',
    });
    generated.push(`includes/blocks/build/${name}/index.js`);

    const javascript = await readFile(outputPath);
    const version = createHash('sha256').update(javascript).digest('hex').slice(0, 20);
    const dependencyPhp = dependencies.map((dependency) => `'${dependency}'`).join(', ');
    await emit(
      `includes/blocks/build/${name}/index.asset.php`,
      `<?php return array('dependencies' => array(${dependencyPhp}), 'version' => '${version}');\n`,
    );

    const metadata = JSON.parse(await readFile(join(sourceRoot, 'block.json'), 'utf8'));
    await emit(
      `includes/blocks/build/${name}/block.json`,
      `${JSON.stringify(metadata, null, 2)}\n`,
    );
  }

  const variantEntry = join(root, 'assets/src/wpcc-variant.js');
  const commonVariantOptions = {
    entryPoints: [variantEntry],
    bundle: true,
    minify: true,
    legalComments: 'none',
    target: ['es2018'],
    charset: 'utf8',
    logLevel: 'silent',
  };
  const esmPath = join(outputRoot, 'assets/dist/wpcc-variant.es.js');
  await mkdir(dirname(esmPath), { recursive: true });
  await build({ ...commonVariantOptions, outfile: esmPath, format: 'esm' });
  generated.push('assets/dist/wpcc-variant.es.js');

  // esbuild does not expose a UMD format. Wrap its deterministic CommonJS
  // output so the legacy filename keeps browser, AMD and CommonJS semantics.
  const [{ text: commonJs }] = (await build({
    ...commonVariantOptions,
    format: 'cjs',
    write: false,
  })).outputFiles;
  const umd = `(function(root,factory){if(typeof module==='object'&&module.exports){module.exports=factory();}else if(typeof define==='function'&&define.amd){define([],factory);}else{root.WPCSVariant=factory();}})(typeof globalThis!=='undefined'?globalThis:this,function(){var module={exports:{}};var exports=module.exports;${commonJs}return module.exports;});\n`;
  await emit('assets/dist/wpcc-variant.umd.js', umd);

  if (checkOnly) {
    const changed = [];
    for (const path of generated.sort()) {
      const expected = await readFile(join(temp, path));
      let actual;
      try {
        actual = await readFile(join(root, path));
      } catch {
        changed.push(path);
        continue;
      }
      if (!actual.equals(expected)) {
        changed.push(path);
      }
    }
    if (changed.length > 0) {
      console.error(`Generated assets are stale:\n${changed.join('\n')}`);
      process.exitCode = 1;
    } else {
      console.log(`Verified ${generated.length} reproducible generated files.`);
    }
  } else {
    console.log(`Generated ${generated.length} files from auditable sources.`);
  }
} finally {
  await rm(temp, { recursive: true, force: true });
}
