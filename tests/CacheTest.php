<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpcc_test_cache'] = [];
        $GLOBALS['wpcc_test_options'] = [];
    }

    public function testModernCacheReturnsConvertedStringAndTracksKey(): void
    {
        $cache = new WPCC_Modern_Cache();
        $cache->cache_conversion('网络', '網路', 'zh-tw', 'opencc');

        self::assertSame('網路', $cache->get_cached_conversion('网络', 'zh-tw', 'opencc'));
        self::assertCount(1, $GLOBALS['wpcc_test_options']['wpcc_cache_keys']);
    }


    public function testCoreCacheSeparatesConversionEngines(): void
    {
        WPCC_Conversion_Cache::set_cached_conversion('后', 'zh-tw', '後', 'opencc');

        self::assertSame('後', WPCC_Conversion_Cache::get_cached_conversion('后', 'zh-tw', 'opencc'));
        self::assertNull(WPCC_Conversion_Cache::get_cached_conversion('后', 'zh-tw', 'mediawiki'));
    }

    public function testBatchCacheSeparatesConversionEngines(): void
    {
        WPCC_Conversion_Cache::set_cached_conversions_batch([
            ['后', 'zh-tw', '後', 'opencc'],
            ['后', 'zh-tw', '后', 'mediawiki'],
        ]);

        self::assertSame('後', WPCC_Conversion_Cache::get_cached_conversion('后', 'zh-tw', 'opencc'));
        self::assertSame('后', WPCC_Conversion_Cache::get_cached_conversion('后', 'zh-tw', 'mediawiki'));
    }

    public function testCoreCacheCanClearWithoutGroupFlushApi(): void
    {
        WPCC_Conversion_Cache::set_cached_conversion('软件', 'zh-tw', '軟體');
        self::assertSame('軟體', WPCC_Conversion_Cache::get_cached_conversion('软件', 'zh-tw'));

        WPCC_Conversion_Cache::clear_all_cache();
        self::assertSame(0, WPCC_Conversion_Cache::get_cache_stats()['memory_cache_size']);
    }
}
