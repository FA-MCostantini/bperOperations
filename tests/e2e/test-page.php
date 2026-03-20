<?php
require_once __DIR__ . '/../../lib/autoloader.php';
$ctl = new \FirstAdvisory\FAWill\controller\operations\ctl_operations();
?>
<!DOCTYPE html>
<html lang="it">
<head><?= $ctl->getHead() ?></head>
<body>
<?= $ctl->getContent() ?>
<?= $ctl->getScript() ?>
</body>
</html>
