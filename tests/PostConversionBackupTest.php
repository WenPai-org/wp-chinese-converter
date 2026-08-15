<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PostConversionBackupTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpcc_options'] = [
            'wpcc_post_conversion_target' => 'zh-tw',
            'wpcc_engine' => 'mediawiki',
        ];
        $GLOBALS['wpcc_test_post_meta'] = [];
        $GLOBALS['wpcc_test_posts'] = [];
        $GLOBALS['wpcc_test_can_edit'] = false;
        $GLOBALS['wpcc_test_history_limit'] = null;
        $GLOBALS['wpcc_test_update_throw'] = false;
        $GLOBALS['wpcc_test_filters'] = [];
    }

    public function testChangedContentCreatesVersionedRollbackHistory(): void
    {
        $converted = $this->convertAndPersist(42, '网络软件', '网络软件');

        self::assertSame('網路軟體', $converted['post_title']);
        $stored = get_post_meta(42, '_wpcc_conversion_history_v2', true);
        self::assertSame(2, $stored['schema']);
        self::assertCount(1, $stored['entries']);
        self::assertSame('网络软件', $stored['entries'][0]['original']['post_content']);
        self::assertSame('wp-cli' === ($stored['entries'][0]['source_context'] ?? '') ? 'wp-cli' : 'admin', $stored['entries'][0]['source_context']);
        self::assertSame([], get_post_meta(42, '_wpcc_conversion_backup', false));
    }

    public function testSlashingBoundaryPreservesQuotesBackslashesJsonAndHtmlAttributes(): void
    {
        $title = <<<'TEXT'
O'Reilly \ 网络软件
TEXT;
        $content = <<<'HTML'
<div data-json='{"path":"C:\\Docs","owner":"O'Reilly"}'>网络软件</div>
HTML;
        $expectedTitle = strtr($title, ['网络' => '網路', '软件' => '軟體']);
        $expectedContent = strtr($content, ['网络' => '網路', '软件' => '軟體']);
        $input = wp_slash([
            'post_type' => 'post',
            'post_title' => $title,
            'post_content' => $content,
        ]);

        $filtered = WPCC_Post_Conversion_Backup::filter_post_data($input, ['ID' => 420]);

        self::assertSame(wp_slash($expectedTitle), $filtered['post_title']);
        self::assertSame(wp_slash($expectedContent), $filtered['post_content']);
        $post = $this->post(420, wp_unslash($filtered['post_title']), wp_unslash($filtered['post_content']));
        $GLOBALS['wpcc_test_posts'][420] = $post;
        WPCC_Post_Conversion_Backup::persist_backup(420, $post);
        $history = get_post_meta(420, '_wpcc_conversion_history_v2', true)['entries'];
        self::assertSame($title, $history[0]['original']['post_title']);
        self::assertSame($content, $history[0]['original']['post_content']);
        self::assertSame($expectedTitle, $history[0]['converted']['post_title']);
        self::assertSame($expectedContent, $history[0]['converted']['post_content']);

        $GLOBALS['wpcc_test_can_edit'] = true;
        kses_init_filters();
        self::assertNotFalse(has_filter('content_save_pre', 'wp_filter_post_kses'));
        $result = wpcc_restore_post_conversion(420, $history[0]['id'], WPCC_Post_Conversion_Backup::hash_post($post), false);
        self::assertTrue($result['success']);
        self::assertSame($title, $GLOBALS['wpcc_test_posts'][420]->post_title);
        self::assertSame($content, $GLOBALS['wpcc_test_posts'][420]->post_content);
        self::assertNotFalse(has_filter('content_save_pre', 'wp_filter_post_kses'));
        self::assertNotFalse(has_filter('title_save_pre', 'wp_filter_kses'));
    }

    public function testIdempotentSaveDoesNotCreateHistory(): void
    {
        $converted = WPCC_Post_Conversion_Backup::filter_post_data([
            'post_type' => 'post',
            'post_title' => '網路軟體',
            'post_content' => '網路軟體',
        ], ['ID' => 43]);
        $post = $this->post(43, $converted['post_title'], $converted['post_content']);
        WPCC_Post_Conversion_Backup::persist_backup(43, $post);

        self::assertSame('', get_post_meta(43, '_wpcc_conversion_history_v2', true));
    }

    public function testHistoryIsBoundedAndHardLimitCanNotExceedFifty(): void
    {
        $GLOBALS['wpcc_test_history_limit'] = 3;
        for ($index = 0; $index < 5; $index++) {
            $this->convertAndPersist(50, '网络软件 ' . $index, '网络软件 ' . $index);
        }
        $history = get_post_meta(50, '_wpcc_conversion_history_v2', true);
        self::assertCount(3, $history['entries']);
        self::assertSame('网络软件 2', $history['entries'][0]['original']['post_title']);

        $GLOBALS['wpcc_test_history_limit'] = 500;
        for ($index = 0; $index < 55; $index++) {
            $this->convertAndPersist(51, '网络软件 ' . $index, '网络软件 ' . $index);
        }
        $history = get_post_meta(51, '_wpcc_conversion_history_v2', true);
        self::assertCount(50, $history['entries']);
    }

    public function testLegacyHistoryMigratesWithoutDeletingLegacyRows(): void
    {
        $legacy = $this->entry('legacy', '网络', '软件', '網路', '軟體');
        unset($legacy['id']);
        add_post_meta(60, '_wpcc_conversion_backup', $legacy, false);
        $GLOBALS['wpcc_test_can_edit'] = true;

        $history = WPCC_Post_Conversion_Backup::get_history(60);

        self::assertCount(1, $history);
        self::assertStringStartsWith('legacy-', $history[0]['id']);
        self::assertCount(1, get_post_meta(60, '_wpcc_conversion_backup', false));
        self::assertSame(2, get_post_meta(60, '_wpcc_conversion_history_v2', true)['schema']);
    }

    public function testSelectedOlderRestoreDryRunAndRestoreUseTheScreenHash(): void
    {
        $first = $this->entry('first', '网络', '软件', '網路', '軟體');
        $second = $this->entry('second', '后台', '打印机', '後台', '印表機');
        $this->seedHistory(61, [$first, $second]);
        $GLOBALS['wpcc_test_posts'][61] = $this->post(61, '後台', '印表機');
        $GLOBALS['wpcc_test_can_edit'] = true;
        $expected = WPCC_Post_Conversion_Backup::hash_post($GLOBALS['wpcc_test_posts'][61]);

        $preview = wpcc_restore_post_conversion(61, 'first', $expected, true);
        $result = wpcc_restore_post_conversion(61, 'first', $expected, false);

        self::assertTrue($preview['success']);
        self::assertTrue($preview['dry_run']);
        self::assertTrue($preview['diff']['post_title']['changed']);
        self::assertTrue($result['success']);
        self::assertSame('网络', $GLOBALS['wpcc_test_posts'][61]->post_title);
    }

    public function testEditAfterDryRunRejectsRestoreUsingTheOldScreenHash(): void
    {
        $first = $this->entry('first-stale', '网络', '软件', '網路', '軟體');
        $second = $this->entry('second-stale', '后台', '打印机', '後台', '印表機');
        $this->seedHistory(611, [$first, $second]);
        $GLOBALS['wpcc_test_posts'][611] = $this->post(611, '後台', '印表機');
        $GLOBALS['wpcc_test_can_edit'] = true;
        $expected = WPCC_Post_Conversion_Backup::hash_post($GLOBALS['wpcc_test_posts'][611]);

        $preview = wpcc_restore_post_conversion(611, 'first-stale', $expected, true);
        $GLOBALS['wpcc_test_posts'][611]->post_title = '预览后编辑';
        $result = wpcc_restore_post_conversion(611, 'first-stale', $expected, false);

        self::assertTrue($preview['success']);
        self::assertSame('wpcc_restore_stale', $result->code);
    }

    public function testSelectedRestoreRequiresPermissionAndExpectedHash(): void
    {
        $entry = $this->entry('selected', '网络', '软件', '網路', '軟體');
        $this->seedHistory(62, [$entry]);
        $GLOBALS['wpcc_test_posts'][62] = $this->post(62, '網路', '軟體');

        self::assertSame('wpcc_forbidden', wpcc_restore_post_conversion(62, 'selected', '', false)->code);
        $GLOBALS['wpcc_test_can_edit'] = true;
        $stale = wpcc_restore_post_conversion(62, 'selected', str_repeat('0', 64), false);
        self::assertSame('wpcc_restore_stale', $stale->code);
    }

    public function testLegacyRestoreStopsAfterLaterEditWithoutScreenHash(): void
    {
        $entry = $this->entry('changed', '网络', '软件', '網路', '軟體');
        $this->seedHistory(63, [$entry]);
        $GLOBALS['wpcc_test_posts'][63] = $this->post(63, '后来编辑', '軟體');
        $GLOBALS['wpcc_test_can_edit'] = true;
        $result = wpcc_restore_post_conversion(63, 'changed', '', false);

        self::assertSame('wpcc_post_changed', $result->code);
    }

    public function testRestoreIsIdempotentAndPreservesHistory(): void
    {
        $entry = $this->entry('repeatable', '网络', '软件', '網路', '軟體');
        $this->seedHistory(64, [$entry]);
        $GLOBALS['wpcc_test_posts'][64] = $this->post(64, '網路', '軟體');
        $GLOBALS['wpcc_test_can_edit'] = true;
        $expected = WPCC_Post_Conversion_Backup::hash_post($GLOBALS['wpcc_test_posts'][64]);

        $first = wpcc_restore_post_conversion(64, 'repeatable', $expected, false);
        $second = wpcc_restore_post_conversion(64, 'repeatable', $expected, false);

        self::assertTrue($first['success']);
        self::assertFalse($first['already_restored']);
        self::assertTrue($second['success']);
        self::assertTrue($second['already_restored']);
        self::assertSame('网络', $GLOBALS['wpcc_test_posts'][64]->post_title);
        self::assertCount(1, get_post_meta(64, '_wpcc_conversion_history_v2', true)['entries']);
    }

    public function testRestoringFlagIsResetWhenWordPressUpdateThrows(): void
    {
        $entry = $this->entry('throws', '网络', '软件', '網路', '軟體');
        $this->seedHistory(65, [$entry]);
        $GLOBALS['wpcc_test_posts'][65] = $this->post(65, '網路', '軟體');
        $GLOBALS['wpcc_test_can_edit'] = true;
        $GLOBALS['wpcc_test_update_throw'] = true;
        kses_init_filters();

        try {
            wpcc_restore_post_conversion(65, 'throws', WPCC_Post_Conversion_Backup::hash_post($GLOBALS['wpcc_test_posts'][65]), false);
            self::fail('Expected simulated update failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('simulated update failure', $exception->getMessage());
        }
        self::assertNotFalse(has_filter('content_save_pre', 'wp_filter_post_kses'));
        self::assertNotFalse(has_filter('title_save_pre', 'wp_filter_kses'));

        $converted = WPCC_Post_Conversion_Backup::filter_post_data([
            'post_type' => 'post',
            'post_title' => '网络软件',
            'post_content' => '网络软件',
        ], ['ID' => 66]);
        self::assertSame('網路軟體', $converted['post_title']);
    }

    public function testBackwardCompatibleLatestRestoreReturnsBoolean(): void
    {
        $entry = $this->entry('latest', '网络', '软件', '網路', '軟體');
        $this->seedHistory(67, [$entry]);
        $GLOBALS['wpcc_test_posts'][67] = $this->post(67, '網路', '軟體');
        $GLOBALS['wpcc_test_can_edit'] = true;

        self::assertTrue(wpcc_restore_latest_post_conversion(67));
    }

    public function testRecoveryControlsTargetASeparateFooterForm(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/includes/wpcc-admin.php');
        $metaBoxStart = strpos($source, 'function wpcc_conversion_meta_box_callback');
        $footerStart = strpos($source, 'function wpcc_render_conversion_restore_form');
        self::assertNotFalse($metaBoxStart);
        self::assertNotFalse($footerStart);

        $metaBox = substr($source, (int) $metaBoxStart, (int) $footerStart - (int) $metaBoxStart);
        $footer = substr($source, (int) $footerStart);
        self::assertStringNotContainsString('<form id="wpcc-conversion-restore-form"', $metaBox);
        self::assertStringContainsString('form="wpcc-conversion-restore-form"', $metaBox);
        self::assertStringContainsString('<form id="wpcc-conversion-restore-form"', $footer);
        self::assertStringContainsString("admin_footer-post.php", $footer);
    }

    /** @return array<string,string> */
    private function convertAndPersist(int $postId, string $title, string $content): array
    {
        $converted = WPCC_Post_Conversion_Backup::filter_post_data(wp_slash([
            'post_type' => 'post',
            'post_title' => $title,
            'post_content' => $content,
        ]), ['ID' => $postId]);
        $post = $this->post($postId, $converted['post_title'], $converted['post_content']);
        $GLOBALS['wpcc_test_posts'][$postId] = $post;
        WPCC_Post_Conversion_Backup::persist_backup($postId, $post);
        return $converted;
    }

    private function post(int $id, string $title, string $content): WP_Post
    {
        $post = new WP_Post();
        $post->ID = $id;
        $post->post_title = $title;
        $post->post_content = $content;
        return $post;
    }

    /** @return array<string,mixed> */
    private function entry(string $id, string $originalTitle, string $originalContent, string $convertedTitle, string $convertedContent): array
    {
        return [
            'id' => $id,
            'original' => ['post_title' => $originalTitle, 'post_content' => $originalContent],
            'converted' => ['post_title' => $convertedTitle, 'post_content' => $convertedContent],
            'target' => 'zh-tw',
            'engine' => 'mediawiki',
            'fingerprint' => hash('sha256', $id),
            'created_at' => time(),
        ];
    }

    /** @param array<int,array<string,mixed>> $entries */
    private function seedHistory(int $postId, array $entries): void
    {
        update_post_meta($postId, '_wpcc_conversion_history_v2', ['schema' => 2, 'entries' => $entries]);
    }
}
