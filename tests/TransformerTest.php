<?php declare(strict_types=1);

namespace WeChatPay\Tests;

use const DIRECTORY_SEPARATOR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function array_map;
use function file_get_contents;
use function json_encode;

use WeChatPay\Transformer;
use PHPUnit\Framework\TestCase;

class TransformerTest extends TestCase
{
    /**
     * @return array<string,array{string,string[]}>
     */
    public function xmlToArrayDataProvider(): array
    {
        $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;

        return [
            'sendredpack.sample.xml' => [
                file_get_contents($baseDir . 'sendredpack.sample.xml') ?: '',
                [
                    'sign', 'mch_billno', 'mch_id', 'wxappid', 'send_name', 're_openid', 'total_amount',
                    'total_num', 'wishing', 'client_ip', 'act_name', 'remark', 'scene_id', 'nonce_str', 'risk_info',
                ],
            ],
            'paysuccess.notification.sample.xml' => [
                file_get_contents($baseDir . 'paysuccess.notification.sample.xml') ?: '',
                [
                    'appid', 'attach', 'bank_type', 'fee_type', 'is_subscribe', 'mch_id', 'nonce_str', 'openid',
                    'out_trade_no', 'result_code', 'return_code', 'sign', 'time_end', 'total_fee', 'coupon_fee',
                    'coupon_count', 'coupon_type', 'coupon_id', 'trade_type', 'transaction_id',
                ],
            ],
            'unifiedorder.sample.xml' => [
                file_get_contents($baseDir . 'unifiedorder.sample.xml') ?: '',
                [
                    'appid', 'attach', 'body', 'mch_id', 'detail', 'nonce_str', 'notify_url', 'openid',
                    'out_trade_no', 'spbill_create_ip', 'total_fee', 'trade_type', 'sign',
                ],
            ],
        ];
    }

    /**
     * @dataProvider xmlToArrayDataProvider
     * @param string $xmlString
     * @param string[] $keys
     */
    public function testToArray(string $xmlString, array $keys): void
    {
        $array = Transformer::toArray($xmlString);

        self::assertIsArray($array);
        self::assertNotEmpty($array);

        array_map(static function($key) use ($array): void {
            static::assertArrayHasKey($key, $array);
            static::assertIsString($array[$key]);
            static::assertStringNotContainsString('<![CDATA[', $array[$key]);
            static::assertStringNotContainsString(']]>', $array[$key]);
        }, $keys);
    }

    /**
     * @return array<string,array{array<mixed>,bool,bool,string,string}>
     */
    public function arrayToXmlDataProvider(): array
    {
        $jsonModifier = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;

        return [
            'normal 1-depth array with extra default options' => [
                [
                    'appid' => 'wx2421b1c4370ec43b',
                    'body' => 'dummybot',
                    'mch_id' => '10000100',
                    'detail' => json_encode([['goods_detail' => '华为手机', 'url' => 'https://huawei.com']], $jsonModifier) ?: ''
                ],
                true, false, 'xml', 'item',
            ],
            'normal 1-depth array with headless=false and indent=true' => [
                [
                    'appid' => 'wx2421b1c4370ec43b',
                    'body' => 'dummybot',
                    'mch_id' => '10000100',
                    'detail' => json_encode([['goods_detail' => '华为手机', 'url' => 'https://huawei.com']], $jsonModifier) ?: ''
                ],
                false, true, 'xml', 'item',
            ],
            '2-depth array with extra default options' => [
                [
                    'appid' => 'wx2421b1c4370ec43b',
                    'body' => 'dummybot',
                    'mch_id' => '10000100',
                    'detail' => [['goods_detail' => '华为手机', 'url' => 'https://huawei.com']],
                ],
                true, false, 'xml', 'item',
            ],
            '2-depth array with with headless=false, indent=true and root=qqpay' => [
                [
                    'appid' => 'wx2421b1c4370ec43b',
                    'body' => 'dummybot',
                    'mch_id' => '10000100',
                    'detail' => [['goods_detail' => '华为手机', 'url' => 'https://huawei.com']],
                ],
                false, true, 'qqpay', 'item',
            ],
        ];
    }

    /**
     * @dataProvider arrayToXmlDataProvider
     * @param array<string,int|string|mixed> $data
     * @param bool $headless
     * @param bool $indent
     * @param string $root
     * @param string $item
     */
    public function testToXml(array $data, bool $headless, bool $indent, string $root, string $item): void
    {
        $xml = Transformer::toXml($data, $headless, $indent, $root, $item);
        self::assertIsString($xml);
        self::assertNotEmpty($xml);

        if ($headless) {
            self::assertStringNotContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        } else {
            self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        }

        if ($indent) {
            self::assertGreaterThanOrEqual(preg_match('#\n#', $xml), 2);
        } else {
            self::assertLessThanOrEqual(preg_match('#\n#', $xml), 0);
        }

        $tag = preg_quote($root);
        $pattern = '#(?:<\?xml[^>]+\?>\n?)?<' . $tag . '>.*</' . $tag . '>\n?#smu';
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression($pattern, $xml);
        } else {
            self::assertRegExp($pattern, $xml);
        }
    }
}