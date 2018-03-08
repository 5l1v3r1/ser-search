<?php

header('Content-type: text/html');
setcookie('config', serialize(range(0,9)));

?>

<html>
	<body>
		<h2>Bem vindo</h2>
		<form>
			<input type="hidden" value="<?= serialize(range(0,9)); ?>">
		</form>
	</body>
</html>
