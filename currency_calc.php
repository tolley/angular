<!doctype html>
<html ng-app>

<head>

<title>Learning AngularJs</title>
<script type="text/javascript" src="/js/angular.js"></script>

</head>

<body>

<div ng-init="qty=1; cost=2">
	<b>Invoice:</b>

	<div>
		Quantity: <input type="number" ng-model="qty" required />
	</div>

	<div>
		Costs: <input type="number" ng-model="cost" required />
	</div>

	<div>
		<b>Total:</b>{{qty * cost | currency}}
	</div>
</div>

</body>

</html>
