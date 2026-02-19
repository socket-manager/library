<?php


if(!function_exists('get_mime_type'))
{
    /**
     * ファイルのMIMEタイプの取得
     * 
     * @param string $p_path ファイルパス
     * @return string MIMEタイプ
     */
    function get_mime_type(string $p_path): string
    {
        $mime_types = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm'
        ];

        // MIMEタイプの取得
        $ext = strtolower(pathinfo($p_path, PATHINFO_EXTENSION));
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';

        return $mime_type;
    }
}

if(!function_exists('get_median'))
{
    /**
     * 中央値（Median）の取得
     * 
     * @param array $p_values サンプル値配列
     * @return int|float|false 中央値
     */
    function get_median(array $p_values): int|float|false
    {
        $cnt = count($p_values);
        if($cnt <= 0)
        {
            return false;
        }
        $vals = $p_values;
        sort($vals);
        $middle = (int) floor($cnt / 2);
        if($cnt % 2)
        {
            // 奇数個 → 真ん中の値
            return $vals[$middle];
        }
        else
        {
            // 偶数個 → 真ん中2つの平均
            return ($vals[$middle - 1] + $vals[$middle]) / 2;
        }
    }
}

if(!function_exists('get_percentile'))
{
    /**
     * パーセンタイル値（Percentile）の取得
     * 
     * @param int $p_rate レート値（90,95など）
     * @param array $p_values サンプル値配列
     * @return int|float|false パーセンタイル値
     */
    function get_percentile(int $p_rate, array $p_values): int|float|false
    {
        if($p_rate < 1 || $p_rate > 100)
        {
            return false;
        }
        $cnt = count($p_values);
        if($cnt <= 0)
        {
            return false;
        }
        $vals = $p_values;
        sort($vals);

        $rate = $p_rate/100;

        // 0〜(n-1) の範囲で位置を計算
        $index = $rate * ($cnt - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        if($lower === $upper)
        {
            return $vals[$lower];
        }
        // 線形補間
        $weight = $index - $lower;
        return $vals[$lower] * (1 - $weight) + $vals[$upper] * $weight;
    }
}

if(!function_exists('get_simple_percentile'))
{
    /**
     * パーセンタイル値（Percentile）の取得（簡易版）
     * 
     * @param int $p_rate レート値（90,95など）
     * @param array $p_values サンプル値配列
     * @return int|float|false パーセンタイル値
     */
    function get_simple_percentile(int $p_rate, array $p_values): int|float|false
    {
        if($p_rate < 1 || $p_rate > 100)
        {
            return false;
        }
        $cnt = count($p_values);
        if($cnt <= 0)
        {
            return false;
        }
        $vals = $p_values;
        sort($vals);

        $rate = $p_rate/100;

        $pos = (int) ceil($rate * $cnt) - 1;
        return $vals[max(0, min($pos, $cnt - 1))];
    }
}

if(!function_exists('get_benchmark'))
{
    /**
     * ベンチマークの取得
     * 
     * @param array $p_values サンプル値配列
     * @return array|false ベンチマーク結果  
     *      avg:アベレージ、mid:中央値、min:最小値、max:最大値、p90:90パーセンタイル値、p95:95パーセンタイル値、total:合計時間
     */
    function get_benchmark(array $p_values): array|false
    {
        $cnt = count($p_values);
        if($cnt <= 0)
        {
            return false;
        }
        $vals = $p_values;
        sort($vals);

        $total = array_sum($vals);
        $avg = $total / $cnt;

        $mid = get_median($vals);
        $p90 = get_percentile(90, $vals);
        $p95 = get_percentile(95, $vals);
        return [
            'avg'   => $avg,
            'mid'   => $mid,
            'min'   => $vals[0],
            'max'   => $vals[$cnt - 1],
            'p90'   => $p90,
            'p95'   => $p95,
            'total' => $total
        ];
    }
}

