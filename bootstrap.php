<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=0.5">

<link rel="stylesheet" href="/css/bootstrap.css">

<style>
	.row {
		background-color: #EEE;
		margin-bottom: 3px;
	}

	.row div {
		text-align: center;
	}

	.test-column {
		border: solid 1px #F00;
	}
</style>

</head>

<body>

<div class="container">

	<div class="row">
		<div class="col-xs-12">
			One Column to rule them all
		</div>
	</div>

	<div class="row">
		<div class="col-sm-2 test-column">
			Left
		</div>

		<div class="col-sm-8 test-column">
			Center
		</div>

		<div class="col-sm-2 test-column">
			Right
		</div>
	</div>

</div>

</body>

</html>