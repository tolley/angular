<!doctype html>
<html>

<head>

<script type="text/javascript" src="/js/angular.js"></script>
<script type="text/javascript">

var myApp = angular.module( 'invoice1', [] );

myApp.controller( 'InvoiceController', function( $scope )
{
	// Define the quantity of our currency
	$scope.qty = 1;

	// Define the total cost of the "item"
	$scope.cost = 2;

	// Define our default input currency and the list of possible output currencies
	$scope.inCurr = 'USD';
	$scope.currencies = ['USD', 'EUR', 'CNY'];

	// Define the exchange rates
	$scope.usdToForeignRates = {
		USD: 1,
		EUR: 0.74,
		CNY: 6.09
	};

	// The method that will call the currency conversion and return the result
	$scope.total = function( outCurr )
	{
		console.log( outCurr );
		return $scope.convertCurrency( $scope.qty * $scope.cost, $scope.inCurr, outCurr );
	};
	
	// The method that will calculate the actual currency conversion amount
	$scope.convertCurrency = function( amount, inCurr, outCurr )
	{
		return amount * ( $scope.usdToForeignRates[outCurr] / $scope.usdToForeignRates[inCurr] );
	};

	// A function call to handle payments
	$scope.pay = function()
	{
		window.alert( 'Thanks!' );
	};
} );
</script>

</head>

<body ng-app="invoice1">

<div ng-controller="InvoiceController">
	<b>Invoice</b>

	<div>
		Quantity: <input type="number" ng-model="qty" required />
	</div>

	<div>
		Costs: <input type="number" ng-model="cost" required />

		<select name="currency" ng-model="inCurr">
			<option ng-repeat="c in currencies">
				{{c}}
			</option>
		</select>

		<div>
			<b>Total:</b>
			<span style="margin-right: 10px;" ng-repeat="c in currencies">
				{{total( c ) | currency:c}}
			</span>

			<button class="btn" ng-click="pay()">
				Pay
			</button>
		</div>
	</div>
</div>

</body>

</html>