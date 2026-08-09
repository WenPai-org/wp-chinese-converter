<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PostConversionBackupTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpcc_options'] = ['wpcc_post_conversion_target' => 'zh-tw'];
        $GLOBALS['wpcc_test_post_meta'] = [];
        $GLOBALS['wpcc_test_posts'] = [];
        $GLOBALS['wpcc_test_can_edit'] = false;
    }

    public function testChangedContentCreatesRollbackBackup(): void
    {
        $converted = WPCC_Post_Conversion_Backup::filter_post_data([
            'post_type' => 'post',
            'post_title' => '网络软件',
            'post_content' => '网络软件',
        ], ['ID' => 42]);

        self::assertSame('網路軟體', $converted['post_title']);
        $post = new WP_Post();
        $post->post_title = $converted['post_title'];
        $post->post_content = $converted['post_content'];
        WPCC_Post_Conversion_Backup::persist_backup(42, $post);

        $backups = get_post_meta(42, '_wpcc_conversion_backup', false);
        self::assertCount(1, $backups);
        self::assertSame('网络软件', $backups[0]['original']['post_content']);
    }

    public function testIdempotentSaveDoesNotCreateBackup(): void
    {
        $converted = WPCC_Post_Conversion_Backup::filter_post_data([
            'post_type' => 'post',
            'post_title' => '網路軟體',
            'post_content' => '網路軟體',
        ], ['ID' => 43]);
        $post = new WP_Post();
        $post->post_title = $converted['post_title'];
        $post->post_content = $converted['post_content'];
        WPCC_Post_Conversion_Backup::persist_backup(43, $post);

        self::assertSame([], get_post_meta(43, '_wpcc_conversion_backup', false));
    }

    public function testRestoreRequiresEditPermissionAndPreservesBackup(): void
    {
        add_post_meta(44, '_wpcc_conversion_backup', [
            'original' => ['post_title' => '网络', 'post_content' => '软件'],
            'converted' => ['post_title' => '網路', 'post_content' => '軟體'],
        ], false);
		$current = new WP_Post();
		$current->ID = 44;
		$current->post_title = '網路';
		$current->post_content = '軟體';
		$GLOBALS['wpcc_test_posts'][44] = $current;

        self::assertInstanceOf(WP_Error::class, wpcc_restore_latest_post_conversion(44));
        $GLOBALS['wpcc_test_can_edit'] = true;
        self::assertTrue(wpcc_restore_latest_post_conversion(44));
        self::assertSame('网络', $GLOBALS['wpcc_test_posts'][44]['post_title']);
        self::assertCount(1, get_post_meta(44, '_wpcc_conversion_backup', false));
    }

	public function testRestoreStopsAfterLaterEdit(): void
	{
		add_post_meta(45, '_wpcc_conversion_backup', [
			'original' => ['post_title' => '网络', 'post_content' => '软件'],
			'converted' => ['post_title' => '網路', 'post_content' => '軟體'],
		], false);
		$current = new WP_Post();
		$current->ID = 45;
		$current->post_title = '后来编辑';
		$current->post_content = '軟體';
		$GLOBALS['wpcc_test_posts'][45] = $current;
		$GLOBALS['wpcc_test_can_edit'] = true;

		$result = wpcc_restore_latest_post_conversion(45);
		self::assertInstanceOf(WP_Error::class, $result);
		self::assertSame('wpcc_post_changed', $result->code);
	}
}
