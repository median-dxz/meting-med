Skip to content
<?php
/**
 * Meting music framework
 * https://i-meto.com
 * https://github.com/metowolf/Meting
 * Version 1.5.7.
 *
 * Copyright 2019, METO Sheel <i@i-meto.com>
 * Released under the MIT license
 */
namespace Metowolf;
class Meting
{
    const VERSION = '1.5.7';
    public $raw;
    public $data;
    public $info;
    public $error;
    public $status;
    public $server;
    public $proxy = null;
    public $format = false;
    public $header;
    public function __construct($value = 'netease')
    {
        $this->site($value);
    }
    public function site($value)
    {
        $suppose = array('netease', 'tencent');
        $this->server = in_array($value, $suppose) ? $value : 'netease';
        $this->header = $this->curlset();
        return $this;
    }
    public function cookie($value)
    {
        $this->header['Cookie'] = $value;
        return $this;
    }
    public function format($value = true)
    {
        $this->format = $value;
        return $this;
    }
    public function proxy($value)
    {
        $this->proxy = $value;
        return $this;
    }
    private function exec($api)
    {
        if (isset($api['encode'])) {
            $api = call_user_func_array(array($this, $api['encode']), array($api));
        }
        if ($api['method'] == 'GET') {
            if (isset($api['body'])) {
                $api['url'] .= '?'.http_build_query($api['body']);
                $api['body'] = null;
            }
        }
        $this->curl($api['url'], $api['body']);
        if (!$this->format) {
            return $this->raw;
        }
        $this->data = $this->raw;
        if (isset($api['decode'])) {
            $this->data = call_user_func_array(array($this, $api['decode']), array($this->data));
        }
        if (isset($api['format'])) {
            $this->data = $this->clean($this->data, $api['format']);
        }
        return $this->data;
    }
    private function curl($url, $payload = null, $headerOnly = 0)
    {
        $header = array_map(function ($k, $v) {
            return $k.': '.$v;
        }, array_keys($this->header), $this->header);
        $curl = curl_init();
        if (!is_null($payload)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($payload) ? http_build_query($payload) : $payload);
        }
        curl_setopt($curl, CURLOPT_HEADER, $headerOnly);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_IPRESOLVE, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if ($this->proxy) {
            curl_setopt($curl, CURLOPT_PROXY, $this->proxy);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->raw = curl_exec($curl);
            $this->info = curl_getinfo($curl);
            $this->error = curl_errno($curl);
            $this->status = $this->error ? curl_error($curl) : '';
            if (!$this->error) {
                break;
            }
        }
        curl_close($curl);
        return $this;
    }
    private function pickup($array, $rule)
    {
        $t = explode('.', $rule);
        foreach ($t as $vo) {
            if (!isset($array[$vo])) {
                return array();
            }
            $array = $array[$vo];
        }
        return $array;
    }
    private function clean($raw, $rule)
    {
        $raw = json_decode($raw, true);
        if (!empty($rule)) {
            $raw = $this->pickup($raw, $rule);
        }
        if (!isset($raw[0]) && count($raw)) {
            $raw = array($raw);
        }
        $result = array_map(array($this, 'format_'.$this->server), $raw);
        return json_encode($result);
    }
    public function search($keyword, $option = null)
    {
        switch ($this->server) {
            case 'netease':
            $api = array(
                'method' => 'POST',
                'url'    => 'http://music.163.com/api/cloudsearch/pc',
                'body'   => array(
                    's'      => $keyword,
                    'type'   => isset($option['type']) ? $option['type'] : 1,
                    'limit'  => isset($option['limit']) ? $option['limit'] : 30,
                    'total'  => 'true',
                    'offset' => isset($option['page']) && isset($option['limit']) ? ($option['page'] - 1) * $option['limit'] : 0,
                ),
                'encode' => 'netease_AESCBC',
                'format' => 'result.songs',
            );
            break;
            case 'tencent':
            $api = array(
                'method' => 'GET',
                'url'    => 'https://c.y.qq.com/soso/fcgi-bin/client_search_cp',
                'body'   => array(
                    'format'   => 'json',
                    'p'        => isset($option['page']) ? $option['page'] : 1,
                    'n'        => isset($option['limit']) ? $option['limit'] : 30,
                    'w'        => $keyword,
                    'aggr'     => 1,
                    'lossless' => 1,
                    'cr'       => 1,
                    'new_json' => 1,
                ),
                'format' => 'data.song.list',
            );
            break;
        }
        return $this->exec($api);
    }
    public function song($id)
    {
        switch ($this->server) {
            case 'netease':
            $api = array(
                'method' => 'POST',
                'url'    => 'http://music.163.com/api/v3/song/detail/',
                'body'   => array(
                    'c' => '[{"id":'.$id.',"v":0}]',
                ),
                'encode' => 'netease_AESCBC',
                'format' => 'songs',
            );
            break;
            case 'tencent':
            $api = array(
                'method' => 'GET',
                'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg',
                'body'   => array(
                    'songmid'  => $id,
                    'platform' => 'yqq',
                    'format'   => 'json',
                ),
                'format' => 'data',
            );
            break;
        }
        return $this->exec($api);
    }
    public function album($id)
    {
        switch ($this->server) {
            case 'netease':
            $api = array(
                'method' => 'POST',
                'url'    => 'http://music.163.com/api/v1/album/'.$id,
                'body'   => array(
                    'total'         => 'true',
                    'offset'        => '0',
                    'id'            => $id,
                    'limit'         => '1000',
                    'ext'           => 'true',
                    'private_cloud' => 'true',
                ),
                'encode' => 'netease_AESCBC',
                'format' => 'songs',
            );
            break;
            case 'tencent':
            $api = array(
                'method' => 'GET',
                'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_album_detail_cp.fcg',
                'body'   => array(
                    'albummid' => $id,
                    'platform' => 'mac',
                    'format'   => 'json',
                    'newsong'  => 1,
                ),
                'format' => 'data.getSongInfo',
            );
            break;
        }
        return $this->exec($api);
    }
    public function artist($id, $limit = 50)
    {
        switch ($this->server) {
            case 'netease':
            $api = array(
                'method' => 'POST',
                'url'    => 'http://music.163.com/api/v1/artist/'.$id,
                'body'   => array(
                    'ext'           => 'true',
                    'private_cloud' => 'true',
                    'ext'           => 'true',
                    'top'           => $limit,
                    'id'            => $id,
                ),
                'encode' => 'netease_AESCBC',
                'format' => 'hotSongs',
            );
            break;
            case 'tencent':
            $api = array(
                'method' => 'GET',
                'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_singer_track_cp.fcg',
                'body'   => array(
                    'singermid' => $id,
                    'begin'     => 0,
                    'num'       => $limit,
                    'order'     => 'listen',
                    'platform'  => 'mac',
                    'newsong'   => 1,
                ),
                'format' => 'data.list',
            );
            break;
        }
        return $this->exec($api);
    }
    public function playlist($id)
    {
        switch ($this->server) {
            case 'netease':
            $api = array(
                'method' => 'POST',
                'url'    => 'http://music.163.com/api/v6/playlist/detail',
                'body'   => array(
                    's'  => '0',
                    'id' => $id,
                    'n'  => '1000',
                    't'  => '0',
                ),
                'encode' => 'netease_AESCBC',
                'format' => 'playlist.tracks',
            );
            break;
            case 'tencent':
            $api = array(
                'method' => 'GET',
                'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_playlist_cp.fcg',
                'body'   => array(
                    'id'       => $id,
                    'format'   => 'json',
                    'newsong'  => 1,
                    'platform' => 'jqspaframe.json',
                ),
                'format' => 'data.cdlist.0.songlist',
            );
            break;
        }
        return $this->exec($api);
    }
    public function url($id, $br = 320)
    {
        switch ($this->server) {
            case 'netease':
            $api = array(
                'method' => 'POST',
                'url'    => 'http://music.163.com/api/song/enhance/player/url',
                'body'   => array(
                    'ids' => array($id),
                    'br'  => $br * 1000,
                ),
                'encode' => 'netease_AESCBC',
                'decode' => 'netease_url',
            );
            break;
            case 'tencent':
            $api = array(
                'method' => 'GET',
                'url'    => 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg',
                'body'   => array(
                    'songmid'  => $id,
                    'platform' => 'yqq',
                    'format'   => 'json',
                ),
                'decode' => 'tencent_url',
            );
            break;
            break;
        }
        $this->temp['br'] = $br;
        return $this->exec($api);
    }
    public function lyric($id)
    {
        switch ($this->server) {
            case 'netease':
            $api = array(
                'method' => 'POST',
                'url'    => 'http://music.163.com/api/song/lyric',
                'body'   => array(
                    'id' => $id,
                    'os' => 'linux',
                    'lv' => -1,
                    'kv' => -1,
                    'tv' => -1,
                ),
                'encode' => 'netease_AESCBC',
                'decode' => 'netease_lyric',
            );
            break;
            case 'tencent':
            $api = array(
                'method' => 'GET',
                'url'    => 'https://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg',
                'body'   => array(
                    'songmid' => $id,
                    'g_tk'    => '5381',
                ),
                'decode' => 'tencent_lyric',
            );
            break;
        }
        return $this->exec($api);
    }
    public function pic($id, $size = 300)
    {
        switch ($this->server) {
            case 'netease':
            $url = 'https://p3.music.126.net/'.$this->netease_encryptId($id).'/'.$id.'.jpg?param='.$size.'y'.$size;
            break;
            case 'tencent':
            $url = 'https://y.gtimg.cn/music/photo_new/T002R'.$size.'x'.$size.'M000'.$id.'.jpg?max_age=2592000';
            break;
        }
        return json_encode(array('url' => $url));
    }
    private function curlset()
    {
        switch ($this->server) {
            case 'netease':
            return array(
                'Referer'         => 'https://music.163.com/',
                'Cookie'          => 'appver=1.5.9; os=osx; __remember_me=true; osver=%E7%89%88%E6%9C%AC%2010.13.5%EF%BC%88%E7%89%88%E5%8F%B7%2017F77%EF%BC%89;',
                'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/605.1.15 (KHTML, like Gecko)',
                'X-Real-IP'       => long2ip(mt_rand(1884815360, 1884890111)),
                'Accept'          => '*/*',
                'Accept-Language' => 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
                'Connection'      => 'keep-alive',
                'Content-Type'    => 'application/x-www-form-urlencoded',
            );
            case 'tencent':
            return array(
                'Referer'         => 'http://y.qq.com',
                'Cookie'          => 'pgv_pvi=22038528; pgv_si=s3156287488; pgv_pvid=5535248600; yplayer_open=1; ts_last=y.qq.com/portal/player.html; ts_uid=4847550686; yq_index=0; qqmusic_fromtag=66; player_exist=1',
                'User-Agent'      => 'QQ%E9%9F%B3%E4%B9%90/54409 CFNetwork/901.1 Darwin/17.6.0 (x86_64)',
                'Accept'          => '*/*',
                'Accept-Language' => 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
                'Connection'      => 'keep-alive',
                'Content-Type'    => 'application/x-www-form-urlencoded',
            );
        }
    }
    private function getRandomHex($length)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }
        if (function_exists('mcrypt_create_iv')) {
            return bin2hex(mcrypt_create_iv($length / 2, MCRYPT_DEV_URANDOM));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }
    }
    private function bchexdec($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; $i++) {
            $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
        }
        return $dec;
    }
    private function bcdechex($dec)
    {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last).$hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);
        return $hex;
    }
    private function str2hex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0'.$hexCode, -2);
        }
        return $hex;
    }
    private function netease_AESCBC($api)
    {
        $modulus = '157794750267131502212476817800345498121872783333389747424011531025366277535262539913701806290766479189477533597854989606803194253978660329941980786072432806427833685472618792592200595694346872951301770580765135349259590167490536138082469680638514416594216629258349130257685001248172188325316586707301643237607';
        $pubkey = '65537';
        $nonce = '0CoJUm6Qyw8W8jud';
        $vi = '0102030405060708';
        if (extension_loaded('bcmath')) {
            $skey = $this->getRandomHex(16);
        } else {
            $skey = 'B3v3kH4vRPWRJFfH';
        }
        $body = json_encode($api['body']);
        if (function_exists('openssl_encrypt')) {
            $body = openssl_encrypt($body, 'aes-128-cbc', $nonce, false, $vi);
            $body = openssl_encrypt($body, 'aes-128-cbc', $skey, false, $vi);
        } else {
            $pad = 16 - (strlen($body) % 16);
            $body = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $nonce, $body.str_repeat(chr($pad), $pad), MCRYPT_MODE_CBC, $vi));
            $pad = 16 - (strlen($body) % 16);
            $body = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $skey, $body.str_repeat(chr($pad), $pad), MCRYPT_MODE_CBC, $vi));
        }
        if (extension_loaded('bcmath')) {
            $skey = strrev(utf8_encode($skey));
            $skey = $this->bchexdec($this->str2hex($skey));
            $skey = bcpowmod($skey, $pubkey, $modulus);
            $skey = $this->bcdechex($skey);
            $skey = str_pad($skey, 256, '0', STR_PAD_LEFT);
        } else {
            $skey = '85302b818aea19b68db899c25dac229412d9bba9b3fcfe4f714dc016bc1686fc446a08844b1f8327fd9cb623cc189be00c5a365ac835e93d4858ee66f43fdc59e32aaed3ef24f0675d70172ef688d376a4807228c55583fe5bac647d10ecef15220feef61477c28cae8406f6f9896ed329d6db9f88757e31848a6c2ce2f94308';
        }
        $api['url'] = str_replace('/api/', '/weapi/', $api['url']);
        $api['body'] = array(
            'params'    => $body,
            'encSecKey' => $skey,
        );
        return $api;
    }
    private function netease_encryptId($id)
    {
        $magic = str_split('3go8&$8*3*3h0k(2)2');
        $song_id = str_split($id);
        for ($i = 0; $i < count($song_id); $i++) {
            $song_id[$i] = chr(ord($song_id[$i]) ^ ord($magic[$i % count($magic)]));
        }
        $result = base64_encode(md5(implode('', $song_id), 1));
        $result = str_replace(array('/', '+'), array('_', '-'), $result);
        return $result;
    }
    private function netease_url($result)
    {
        $data = json_decode($result, true);
        if (isset($data['data'][0]['uf']['url'])) {
            $data['data'][0]['url'] = $data['data'][0]['uf']['url'];
        }
        if (isset($data['data'][0]['url'])) {
            $url = array(
                'url'  => $data['data'][0]['url'],
                'size' => $data['data'][0]['size'],
                'br'   => $data['data'][0]['br'] / 1000,
            );
        } else {
            $url = array(
                'url'  => '',
                'size' => 0,
                'br'   => -1,
            );
        }
        return json_encode($url);
    }
    private function tencent_url($result)
    {
        $data = json_decode($result, true);
        $guid = mt_rand() % 10000000000;
        $type = array(
            array('size_320mp3', 320, 'M800', 'mp3'),
            array('size_192aac', 192, 'C600', 'm4a'),
            array('size_128mp3', 128, 'M500', 'mp3'),
            array('size_96aac', 96, 'C400', 'm4a'),
            array('size_48aac', 48, 'C200', 'm4a'),
            array('size_24aac', 24, 'C100', 'm4a'),
        );
        $payload = array(
            'req_0' => array(
                'module' => 'vkey.GetVkeyServer',
                'method' => 'CgiGetVkey',
                'param'  => array(
                    'guid'      => (string) $guid,
                    'songmid'   => array(),
                    'filename'  => array(),
                    'songtype'  => array(),
                    'uin'       => '0',
                    'loginflag' => 1,
                    'platform'  => '20',
                ),
            ),
        );
        foreach ($type as $vo) {
            $payload['req_0']['param']['songmid'][] = $data['data'][0]['mid'];
            $payload['req_0']['param']['filename'][] = $vo[2].$data['data'][0]['file']['media_mid'].'.'.$vo[3];
            $payload['req_0']['param']['songtype'][] = $data['data'][0]['type'];
        }
        $api = array(
            'method' => 'GET',
            'url'    => 'https://u.y.qq.com/cgi-bin/musicu.fcg',
            'body'   => array(
                'format'      => 'json',
                'platform'    => 'yqq.json',
                'needNewCode' => 0,
                'data'        => json_encode($payload),
            ),
        );
        $response = json_decode($this->exec($api), true);
        $vkeys = $response['req_0']['data']['midurlinfo'];
        foreach ($type as $index => $vo) {
            if ($data['data'][0]['file'][$vo[0]] && $vo[1] <= $this->temp['br']) {
                if (!empty($vkeys[$index]['vkey'])) {
                    $url = array(
                        'url'  => $response['req_0']['data']['sip'][0].$vkeys[$index]['purl'],
                        'size' => $data['data'][0]['file'][$vo[0]],
                        'br'   => $vo[1],
                    );
                    break;
                }
            }
        }
        if (!isset($url['url'])) {
            $url = array(
                'url'  => '',
                'size' => 0,
                'br'   => -1,
            );
        }
        return json_encode($url);
    }
    private function netease_lyric($result)
    {
        $result = json_decode($result, true);
        $data = array(
            'lyric'  => isset($result['lrc']['lyric']) ? $result['lrc']['lyric'] : '',
            'tlyric' => isset($result['tlyric']['lyric']) ? $result['tlyric']['lyric'] : '',
        );
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    private function tencent_lyric($result)
    {
        $result = substr($result, 18, -1);
        $result = json_decode($result, true);
        $data = array(
            'lyric'  => isset($result['lyric']) ? base64_decode($result['lyric']) : '',
            'tlyric' => isset($result['trans']) ? base64_decode($result['trans']) : '',
        );
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    private function format_netease($data)
    {
        $result = array(
            'id'       => $data['id'],
            'name'     => $data['name'],
            'artist'   => array(),
            'album'    => $data['al']['name'],
            'pic_id'   => isset($data['al']['pic_str']) ? $data['al']['pic_str'] : $data['al']['pic'],
            'url_id'   => $data['id'],
            'lyric_id' => $data['id'],
            'source'   => 'netease',
        );
        if (isset($data['al']['picUrl'])) {
            preg_match('/\/(\d+)\./', $data['al']['picUrl'], $match);
            $result['pic_id'] = $match[1];
        }
        foreach ($data['ar'] as $vo) {
            $result['artist'][] = $vo['name'];
        }
        return $result;
    }
    private function format_tencent($data)
    {
        if (isset($data['musicData'])) {
            $data = $data['musicData'];
        }
        $result = array(
            'id'       => $data['mid'],
            'name'     => $data['name'],
            'artist'   => array(),
            'album'    => trim($data['album']['title']),
            'pic_id'   => $data['album']['mid'],
            'url_id'   => $data['mid'],
            'lyric_id' => $data['mid'],
            'source'   => 'tencent',
        );
        foreach ($data['singer'] as $vo) {
            $result['artist'][] = $vo['name'];
        }
        return $result;
    }
}