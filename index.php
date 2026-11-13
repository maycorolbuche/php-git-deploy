<?php
require_once('telegram.php');

$get_remote = filter_input(INPUT_GET, 'remote', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$force = filter_input(INPUT_GET, 'force', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!defined('TIME_LIMIT')) define('TIME_LIMIT', 30);

if ($status != "true") {

    $cmd = [];

    $i = 0;
    $deploy = json_decode(file_get_contents('deploy.json'), true);
    if ($get_remote <> "") {
        $deploy = array_filter($deploy, function ($d) use ($get_remote) {
            return $d['remote_repository'] === $get_remote;
        });
    }
    foreach ($deploy as $d) {
        $remoteRepository = $d['remote_repository'];
        $localDir = $d['local_dir'];
        $branch = escapeshellarg($d['branch']);
        $token = $d['token'] ?? '';
        $remoteUrl = 'https://github.com/' . $remoteRepository;
        $directory = $localDir !== '' ? 'cd ' . escapeshellarg($localDir) . ' && ' : '';

        $git = 'GIT_TERMINAL_PROMPT=0 git';
        if ($token !== '') {
            // Supply the token only to Git's credential helper, without writing it to origin.
            $helper = <<<'SH'
!f() { if [ "$1" = get ]; then printf 'username=x-access-token\npassword=%s\n' "$PHP_GIT_DEPLOY_TOKEN"; fi; }; f
SH;
            $git .= ' -c credential.helper= -c credential.useHttpPath=true -c '
                . escapeshellarg('credential.' . $remoteUrl . '.helper=' . $helper);
        }

        $cmd[$i]['data'] = $d;
        $cmd[$i]['commands'] = [];

        if ($localDir !== '' && !is_dir($localDir)) {
            $cmd[$i]['commands'][] = [
                $git . ' clone --depth=1 --branch ' . $branch . ' -- '
                    . escapeshellarg($remoteUrl) . ' ' . escapeshellarg($localDir),
                true,
            ];
        } else {
            $cmd[$i]['commands'][] = [
                $directory . 'git remote set-url origin ' . escapeshellarg($remoteUrl),
                false,
            ];
            $cmd[$i]['commands'][] = [
                $directory . 'git reset --hard ' . escapeshellarg('origin/' . $d['branch']),
                false,
            ];
            $cmd[$i]['commands'][] = [
                $directory . $git . ' pull ' . ($force === 'true' ? '--force ' : '') . 'origin ' . $branch,
                true,
            ];
        }

        foreach ($d['commands'] ?? [] as $command) {
            $cmd[$i]['commands'][] = [$directory . $command, false];
        }

        $i++;
    }
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
if ($status != "true") {
    $output = "";
    foreach ($cmd as $data) {
        echo "<span class='title'># " . $data["data"]["remote_repository"] . "</span> ";
        echo "<span class='branch'>" . $data["data"]["branch"] . "</span>\n";

        $telegram_message = "";
        $telegram_message .= "🟢 <b>" . $data["data"]["remote_repository"] . "</b>\n";
        $telegram_message .= "🔗 https://github.com/" . $data["data"]["remote_repository"] . "\n";
        $telegram_message .= "🔅 " . $data["data"]["branch"] . "\n";

        $token = $data['data']['token'] ?? '';
        foreach ($data["commands"] as [$command, $authenticate]) {
            $output = "";

            set_time_limit(TIME_LIMIT);
            $tmp = array();
            $useToken = $authenticate && $token !== '';
            if ($useToken) {
                $previousToken = getenv('PHP_GIT_DEPLOY_TOKEN');
                putenv('PHP_GIT_DEPLOY_TOKEN=' . $token);
            }

            try {
                exec($command . ' 2>&1', $tmp, $return_code);
            } finally {
                if ($useToken) {
                    putenv($previousToken === false
                        ? 'PHP_GIT_DEPLOY_TOKEN'
                        : 'PHP_GIT_DEPLOY_TOKEN=' . $previousToken);
                }
            }

            $loggedCommand = trim($command);
            $loggedOutput = trim(implode("\n", $tmp));
            // Hide credentials embedded in legacy URLs or echoed by a command.
            $loggedCommand = preg_replace('~(https?://)[^/\s@]+@~i', '$1[redacted]@', $loggedCommand);
            $loggedOutput = preg_replace('~(https?://)[^/\s@]+@~i', '$1[redacted]@', $loggedOutput);
            if ($token !== '') {
                $secrets = [$token, rawurlencode($token)];
                $loggedCommand = str_replace($secrets, '[redacted]', $loggedCommand);
                $loggedOutput = str_replace($secrets, '[redacted]', $loggedOutput);
            }
            printf(
                '<span class="prompt">$</span> <span class="command">%s</span><div class="output">%s</div>',
                htmlentities($loggedCommand),
                htmlentities($loggedOutput)
            );

            $telegram_message .= "<pre>" . htmlspecialchars($loggedCommand) . "</pre>\n";
            $telegram_message .= "<code>" .  htmlspecialchars($loggedOutput) . "</code>\n\n";

            $output .= ob_get_contents();
            ob_flush();

            // Error handling and cleanup
            if ($return_code !== 0) {
                printf(
                    '<div class="error">Error encountered! code: %s</div>',
                    $return_code
                );
                $telegram_message = preg_replace('/^🟢/u', '🔴', $telegram_message);
                break;
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
} else {
    echo "Ok";
}
?>
    </pre>
</body>

</html>