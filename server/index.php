<?php

// generates JSON on the fly as expected by https://github.com/mikalhart/ESP32-OTA-Pull.git,
// given a directory organisation like:
// some-app/    <-- place this script as index.php here
//            /Board1
//            /ESP32_DEV
//                      /firmware_1.2.4.bin
//                      /firmware_2.3.7.bin
//                      /30:C6:F7:24:AC:38
//                                        /firmware_1.2.3.bin
//                      /12:34:56:F7:24:AC
//                                        /firmware_2.3.7.bin
//                                        /firmware_2.3.9.bin
//
// walks the subdirectories to generate what OTA-Pull expects - like so:
// {
//     "Configurations": [
//       {
//         "Board": "ESP32_DEV",
//         "Version": "1.0.0",
//         "Device": "30:C6:F7:24:AC:38",
//         "URL": "https://example.com/images/Basic-OTA-Example-ESP32_DEV-1.0.0.bin"
//       }
//     ]
//   }
//
// Extensions: 
//  Application (toplevel dirname)
//  Upload: rc822 datetime of upload 
// {
//     "Configurations": [
//         {
//             "Application": "some-app",
//             "Board": "m5stack-core2",
//             "Version": "0.0.3",
//             "Upload": "Sat, 06 May 2023 17:42:15 +0000",
//             "URL": "https://example.com/images/some-app/m5stack-core2/firmware_0.0.3.bin"
//         },
//         {
//             "Application": "some-app",
//             "Device": "30:C6:F7:24:AC:38",
//             "Board": "m5stack-core2",
//             "Version": "2.1.3",
//             "Upload": "Sat, 06 May 2023 17:56:21 +0000",
//             "URL": "https://example.com/images/some-app/m5stack-core2/30:C6:F7:24:AC:38/firmware_2.2.0.bin"
//         }
//     ]
// }
$directoryToScan = "/var/www/example.com/images/*";
define('DOWNLOAD', "https://example.com");

header('Content-Type: application/json; charset=utf-8');

function getVersion($str)
{
    preg_match("/((?:[0-9]+\.[0-9]+\.[0-9]+?)+)/i", $str, $matches);
    if (sizeof($matches, 0)) {
        return $matches[1];
    }
}

function macAdress($str)
{
    preg_match("/\A(([0-9A-Fa-f][0-9A-Fa-f]:)+[0-9A-Fa-f][0-9A-Fa-f])\z/i", $str, $matches);
    if (sizeof($matches, 0)) {
        return $matches[0];
    }
}

$base = dirname($_SERVER['DOCUMENT_URI']);
$chunks = explode('/', $base);
$idxLast = count($chunks) - 1;
$application = $chunks[$idxLast];
$json_array = array();
date_default_timezone_set('UTC');

$pattern = "*";
foreach (glob($pattern, GLOB_ONLYDIR) as $board) {
    $json_array = array();

    foreach (glob($board . "/*.bin") as $filename) {
        $fn = basename($filename);
        $fileDate = date(DATE_RFC2822, filectime($filename));   
        $version = getVersion($fn);
        $dir = basename($board);
        $turl = DOWNLOAD . $base . "/" . $board . "/" . $fn;
        $url = str_replace(' ', '%20', $turl);

        $json_array[] = array(
            'Application' => $application,
            'Board' => $board,
            'Version' => $version,
            'Upload' => $fileDate,
            'URL' => $url
        );

    }
    foreach (glob($board . "/*", GLOB_ONLYDIR) as $path) {
        $mac = basename($path);
        $macaddr = macAdress($mac);
        if ($macaddr) {
            foreach (glob($board . "/" . $macaddr . "/*.bin") as $filename) {
                $fn = basename($filename);
                $fileDate = date(DATE_RFC2822, filectime($filename));   
                $turl = DOWNLOAD . $base . "/" . $board . "/" . $macaddr . "/" . $fn;
                $url = str_replace(' ', '%20', $turl);

                $json_array[] = array(
                    'Application' => $application,
                    'Device' => $macaddr,
                    'Board' => $board,
                    'Version' => $version,
                    'Upload' => $fileDate,
                    'URL' => $url
                );
            }
        }
    }
}
$config = array("Configurations" => $json_array);

echo (json_encode($config, JSON_PRETTY_PRINT |
    JSON_UNESCAPED_SLASHES));

?>