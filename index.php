<?php

/**
 * @author  aulianza
 * @website  https://aulianza.id
 * @version 1.0
 * @base   node.js & php 7
 */
 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
date_default_timezone_set("Asia/Jakarta");

session_start();
    
$url_set_cookie     = 'http://103.41.169.42';
$url_set_absence    = 'http://103.41.169.42/index.php/halaman_control/absenOnline';

$nip    = $_GET['nip']; //get from query params nip
$pass   = $_GET['pass']; //get from query params pass

$auth_data = [
    'username'  => $nip,
    'password'  => $pass,
    'angkanya'  => 123, //random aja karna ngeceknya di client-side
];

$absence_data = [
    'Proses'    => 'Proses'
];

if (empty($nip)) {
    echo json_encode([
        'status'    => 400,
        'message'   => 'NIP tidak boleh kosong'
    ]);
} else if (empty($pass)) {
    echo json_encode([
        'status'    => 400,
        'message'   => 'Password wajib diisi'
    ]);
} else {
    
    $date       = $date = date('m/d/Y H:i:s', time());
    $timestamp  = strtotime($date);
    $weekday    = date("l", $timestamp );
    $hari_idn   = $weekday == 'Saturday' ? 'Sabtu' : 'Minggu';

    if ($weekday == "Saturday" || $weekday == "Sunday") {
        echo json_encode([
            'status'    => 500,
            'message'   => 'Hari ini '. $hari_idn . ', liburan dulu :)'
        ]); 
    // } else if (hari libur lain based on calendar) {
    //     disini bisa tambahin lagi kondisi buat ngecek tanggal merah/hari libur berdasarkan kalender
    } else {
        
        $ch = curl_init ($url_set_cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $auth_data);
        $result = curl_exec($ch);
        $info   = curl_getinfo($ch);
        
        if (!$cookies) {
            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
            $cookies = array();
            foreach($matches[1] as $item) {
                parse_str($item, $cookie);
                $cookies = array_merge($cookies, $cookie);
            }
            $cookie_name = 'ci_session';
            if (!isset($_COOKIE['ci_session'])) {
                setcookie($cookie_name, $cookies['ci_session'], time() + (86400 * 30), "/");
                $_COOKIE['ci_session'] = $cookies['ci_session'];
            }
            foreach(explode('; ',$_SERVER['HTTP_COOKIE']) as $rawcookie)
            {
                list($k,$v) = explode('=',$rawcookie, 2);
                $_RAWCOOKIE[$k] = $v;
            }
            $curl = curl_init();
            curl_setopt_array($curl, [
              CURLOPT_URL => $url_set_absence,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => $absence_data,
              CURLOPT_COOKIE => "ci_session=".$_RAWCOOKIE['ci_session']."; PHPSESSID=".$_COOKIE['PHPSESSID'],
              CURLOPT_HTTPHEADER => [
                "Content-Type: multipart/form-data",
                "content-type: multipart/form-data; boundary=---011000010111000001101001"
              ],
            ]);
            
            $response   = curl_exec($curl);
            $info2      = curl_getinfo($curl);
            $err        = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
                echo json_encode(array(
                    'status'    => '500',
                    'message'   => 'cURL Error'
                ));
            } else {
                if ($info2['download_content_length'] == 2038) {
                    header("Location: http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");exit;
                } else {
                    setAbsence($response, $nip);
                }
            }
        }
        
    }
}

// function to set absence
function setAbsence($res, $nip) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument;
    if ($dom->loadHTML($res, LIBXML_NOWARNING)){
        foreach($dom->getElementsByTagName('p') as $el) {
            $anchor = $el->nodeValue;
            echo json_encode(
                [
                    'status'    => 200,
                    'message'   => $anchor,
                    'data'      => [
                        'NIP'    => $nip
                    ]
                ]
            );
        }
    } else {
        echo json_encode([
            'status'    => 401,
            'message'   => 'NIP atau password salah'
        ]);
    }
    removeCookies();
}

// function to unset cookies
function removeCookies() {
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time()-1000);
            setcookie($name, '', time()-1000, '/');
        }
    }
}