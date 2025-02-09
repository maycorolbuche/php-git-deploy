<?php
$cmd = [];

$deploy = json_decode(file_get_contents('deploy.json'), true);
foreach ($deploy as $d) {
    $remote_repository = $d['remote_repository'];
    $local_dir = $d['local_dir'];
    $branch = $d['branch'];

    if (!is_dir($local_dir)) {
        $cmd[] = sprintf(
            'git clone --depth=1 --branch %s https://github.com/%s %s',
            $branch,
            $remote_repository,
            $local_dir
        );
    } else {
        $cmd[] = sprintf(
            'cd %s && git pull origin %s',
            $local_dir,
            $branch
        );
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Simple PHP Git deploy script</title>
    <style>
        body {
            padding: 0 1em;
            background: #222;
            color: #fff;
        }

        h2,
        .error {
            color: #c33;
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
foreach ($cmd as $c) {
    echo "<span class='prompt'>$</span> <span class='command'>$c</span><br>\n";
    $output = shell_exec($c);
    echo "<span class='output'>$output</span><br>\n";
}
?>
    </pre>
</body>

</html>