<?php
$get_remote = filter_input(INPUT_GET, 'remote', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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

    $cmd[$i]['data'] = $d;
    $cmd[$i]['commands'] = [];

    if ($local_dir <> "" && !is_dir($local_dir)) {
        $cmd[$i]['commands'][] = sprintf(
            'git clone --depth=1 --branch %s https://github.com/%s %s',
            $branch,
            $remote_repository,
            $local_dir
        );
    } else {
        if ($local_dir <> "") {
            $cmd[$i]['commands'][] = sprintf(
                'cd %s && git pull origin %s',
                $local_dir,
                $branch
            );
        } else {
            $cmd[$i]['commands'][] = sprintf(
                'git pull origin %s',
                $branch
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
    foreach ($data["commands"] as $c) {
        echo "<span class='prompt'>$</span> <span class='command'>$c</span>\n";
        $output = shell_exec($c);
        if ($output <> "") {
            echo "<span class='output'>$output</span>\n";
        } else {
            echo "<span class='error'>Error</span>\n";
        }
    }
    echo "\n";
}
?>...
    </pre>
</body>

</html>