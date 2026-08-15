<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ConverterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpcc_test_cache'] = [];
        WPCC_Converter_Factory::clear_cache();
        WPCC_Conversion_Cache::clear_all_cache();
    }

    public function testOpenCcRegionalVocabulary(): void
    {
        $converter = WPCC_Converter_Factory::get_converter('opencc');
        $source = '鼠标里面的软件开发服务器打印机网络后台';

        self::assertSame('滑鼠裡面的軟體開發伺服器印表機網路後臺', $converter->convert($source, 'zh-tw'));
        self::assertSame('鼠標裏面的軟件開發服務器打印機網絡後台', $converter->convert($source, 'zh-hk'));
        self::assertSame($source, $converter->convert($source, 'zh-cn'));
    }

    public function testOpenCcLeavesMarkupAndAsciiIdentifiersStable(): void
    {
        $converter = WPCC_Converter_Factory::get_converter('opencc');
        $source = '<div data-key="network_id">网络软件</div>';

        self::assertSame('<div data-key="network_id">網路軟體</div>', $converter->convert($source, 'zh-tw'));
    }

    public function testMediaWikiConverterSupportsDocumentedVariants(): void
    {
        $converter = WPCC_Converter_Factory::get_converter('mediawiki');

        self::assertSame(['zh-hans', 'zh-hant', 'zh-cn', 'zh-tw', 'zh-hk', 'zh-sg'], $converter->get_supported_variants());
        self::assertNotSame('网络软件', $converter->convert('网络软件', 'zh-tw'));
        self::assertSame('网络软件', $converter->convert('网络软件', 'zh-jp'));
    }
}
