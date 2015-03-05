<!DOCTYPE html>
<html ng-app="myApp">

<head>

<meta name="viewport" content="width=device-width, height=device-height, user-scalable=no">

<title>Angular.js and HTML5 version of Tetris</title>

<script src="/js/hammer.js"></script>
<script src="/js/angular.js"></script>
<script src="/js/tetris.js"></script>

<link rel="stylesheet" type="text/css" href="/css/tetris.css" media="screen, projection">

</head>

<body ng-controller="tetrisController" ng-keydown="onKeyDown( $event )">

<tetris></tetris>

<br />
<div id="controls" class="mobile_only">
	Controls:
	<br />
	D: Rotate current blockcounter clockwise
	<br />
	R: Rotate current clockwise
	<br />
	Up Arrow: Drop current block
	<br />
	Down Arrow: Lower current block on row
	<br />
	Left Arrow: Move current block left
	<br />
	Right Arrow: Move current block right
	<br />
	S: Swap current block
	<br />
	Space bar: Toggle Pause
	<br />
	Q: Restart game
</div>

</body>

</html>