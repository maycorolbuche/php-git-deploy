<?php
require_once('telegram.php');

$get_remote = filter_input(INPUT_GET, 'remote', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$force = filter_input(INPUT_GET, 'force', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!defined('TIME_LIMIT')) define('TIME_LIMIT', 30);



$cmd = [];

$i = 0;
$deploy = json_decode(file_get_contents('deploy.json'), true);
if ($get_remote <> "") {
    $deploy = array_filter($deploy, function ($d) use ($get_remote) {
        return $d['remote_repository'] === $get_remote;
    });
}
foreach ($deploy as $d) {
    $remote_repository = $d['remote_repository'];
    $local_dir = $d['local_dir'];
    $branch = $d['branch'];
    $token = $d['token'];
    $commands = $d['commands'] ?? [];

    $cmd[$i]['data'] = $d;
    $cmd[$i]['commands'] = [];

    if ($local_dir <> "" && !is_dir($local_dir)) {
        $cmd[$i]['commands'][] = sprintf(
            'git clone --depth=1 --branch %s https://%sgithub.com/%s %s',
            $branch,
            ($token <> "") ? "$token@" : "",
            $remote_repository,
            $local_dir
        );
    } else {
        if ($local_dir <> "") {
            $cmd[$i]['commands'][] = sprintf(
                'cd %s && git reset --hard',
                $local_dir
            );

            $cmd[$i]['commands'][] = sprintf(
                'cd %s && git pull origin %s %s',
                $local_dir,
                $branch,
                ($force == "true") ? "--force" : ""
            );
        } else {
            $cmd[$i]['commands'][] = sprintf(
                'git reset --hard'
            );

            $cmd[$i]['commands'][] = sprintf(
                'git pull origin %s %s',
                $branch,
                ($force == "true") ? "--force" : ""
            );
        }
    }

    foreach ($commands as $command) {
        if ($local_dir <> "") {
            $cmd[$i]['commands'][] = sprintf(
                'cd %s && %s',
                $local_dir,
                $command
            );
        } else {
            $cmd[$i]['commands'][] = sprintf(
                '%s',
                $command
            );
        }
    }

    $i++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Logs</title>
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">

    <style>
        body {
            padding: 0 1em;
            background: #222;
            color: rgb(255, 255, 255);
        }

        .error {
            color: #c33;
        }

        .title {
            color: rgb(255, 217, 1);
        }

        .branch {
            color: rgb(34, 34, 34);
            background: rgb(255, 217, 1);
            padding: 0 10px;
            border-radius: 4px;
        }

        .prompt {
            color: #6be234;
        }

        .command {
            color: #729fcf;
        }

        .output {
            color: #999;
        }
    </style>
</head>

<body>
    <pre>

<?php
foreach ($cmd as $data) {
    echo "<span class='title'># " . $data["data"]["remote_repository"] . "</span> ";
    echo "<span class='branch'>" . $data["data"]["branch"] . "</span>\n";

    $telegram_message = "";
    $telegram_message .= "🟢 <b>" . $data["data"]["remote_repository"] . "</b>\n";
    $telegram_message .= "🔗 https://github.com/" . $data["data"]["remote_repository"] . "\n";
    $telegram_message .= "🔅 " . $data["data"]["branch"] . "\n";

    foreach ($data["commands"] as $command) {

        set_time_limit(TIME_LIMIT);
        $tmp = array();
        exec($command . ' 2>&1', $tmp, $return_code);
        printf(
            '<span class="prompt">$</span> <span class="command">%s</span><div class="output">%s</div>',
            htmlentities(trim($command)),
            htmlentities(trim(implode("\n", $tmp)))
        );

        $telegram_message .= "<pre>" . trim($command) . "</pre>\n";
        $telegram_message .= "<code>" .  trim(implode("\n", $tmp)) . "</code>\n\n";

        $output .= ob_get_contents();
        ob_flush();

        // Error handling and cleanup
        if ($return_code !== 0) {
            printf(
                '<div class="error">Error encountered! code: %s</div>',
                $return_code
            );
        }
        echo "\n";
    }
    echo "\n";

    if ($get_remote <> "") {
        foreach ($data["data"]["telegram"] ?? [] as $telegram) {
            sendTelegramMessage($telegram["bot_token"], $telegram["chat_id"], $telegram_message);
        }
    }
}
?>
    </pre>
</body>

</html>