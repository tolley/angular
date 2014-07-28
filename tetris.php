<!DOCTYPE html>
<html ng-app="myApp">

<head>

<title>Angular.js and HTML5 version of Tetris</title>

<script type="text/javascript" src="/js/angular.js"></script>

<script type="text/javascript">
"use strict";

// http://www.ng-newsletter.com/posts/beginner2expert-services.html

// Define our timer object (should probably fit somewhere into angular)
var timer = ( function()
{
	return {
		// Returns the amount of time in milliseconds that have elapsed since the epoch
		getCurrentTime: ( function()
		{
			// Get the current date
			var date = new Date();

			// Convert the current date to seconds/milliseconds
			return Date.parse( date.toISOString() );
		} )
	};
} () );

// ToDo: Learn Laravel and/or symphony php framework

// Define our main app
var app = angular.module( 'myApp', [] );

// Define our tetris playing app
app.factory( 'tetrisGame', function()
{
	var tetrisGame = {
		// A variable to keep track of whether or not the game has been initialized
		initialized: false,

		// Returns a random number between min and max inclusive
		rand: function( min, max )
		{
			return Math.floor( Math.random() * ( max + min ) );
		},

		// A method to initialize this instance of the game
		initialize: function( boardId )
		{
			// If the game has already been initialized, return
			if( this.initialized )
				return;

			var self = this;

			// Get the canvas element that will display the game state
			this.canvasElem = document.getElementById( boardId );

			// If we where unable to get the canvas element, return
			if( ! this.canvasElem || ! this.canvasElem.getContext )
			{
				alert( 'Unable to find canvas element' );
				return;
			}

			// Get the 2d drawing context for our canvas
			this.context = this.canvasElem.getContext( '2d' );

			// Get the height and width of the canvas
			this.width = this.canvasElem.width;
			this.height = this.canvasElem.height;

			// Calculate the width and height of a block
			this.blockWidth = this.width / 10;
			this.blockHeight = this.height / 20;

			// The speed for steps between block falls
			this.step = 300;// 1000;

			// The counter that tells us when to move onto the next tetromino
			this.tetrominoLockCounter = 0;

			// The maximum time to wait to lock the current tetromino
			this.tetrominoLockTimeout = this.step;

			// A flag to indicate the lock countdown is running
			this.tetrominoLockCountdownRunning = false;

			// The game's timer
			this.currentTime = timer.getCurrentTime(),
			this.elapsedTime = 0;

			// The actual board (Note: it's sideways to x will be the horizontal coord and y will be the vertical coord)
			this.board = Array(
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
				Array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 )
			);

			// A list of all possible blocks (Note: Need to figure out a center block for each tetromino)
			this.possibleTetrominoes = Array(
				Array( [0,0], [0,1], [0,2], [0,3] ), // i shaped block
				Array( [1,0], [1,1], [0,2], [1,2] ), // j shaped block
				Array( [0,0], [0,1], [0,2], [1,2] ), // l shaped block
				Array( [0,0], [1,0], [0,1], [1,1] ), // o shaped block
				Array( [0,0], [1,0], [1,1], [2,1] ), // z shaped block
				Array( [1,0], [0,1], [1,1], [2,1] ), // t shaped block
				Array( [0,1], [1,0], [1,1], [2,0] )  // s shaped block
			);

			// A list of all possible tetromino colors
			this.tetrominoColors = Array(
				'#FF0000',
				'#00FF00',
				'#0000FF'
			);

			// The current tetromino that is falling
			this.currentTetromino = this.generateTetromino();

			// The next tetromino this will fall
			this.nextTetromino = this.generateTetromino();

			// The board's background color
			this.bgcolor = '#000000';

			// Clear our canvas
			this.canvasElem.width = this.width;
			this.canvasElem.height = this.height;

			// Draw a rectangle on our canvas
			this.context.fillStyle = this.bgcolor;
			this.context.fillRect( 0, 0, this.width, this.height );

			// A variable used to pause the game
			this.paused = false;

			// Set the initialized flag
			this.initialized = true;

/*			// Draw a random block
			var blockCoords = this.possibleTetrominoes[5];
			this.context.fillStyle = '#FF0000';
			this.context.strokeStyle = '#FF0000';

			for( var n = 0; n < blockCoords.length; ++n )
			{
				var xCoord = blockCoords[n][0];
				var yCoord = blockCoords[n][1];

				// Try rotating the block
//				x' = x * cos(PI/2) - y * sin(PI/2) and y' = x * sin(PI/2) + y * cos(PI/2)
				var xCoordOrig = xCoord;
				var yCoordOrig = yCoord;
				xCoord = Math.round( xCoordOrig * Math.cos( 3.1415 / 2 ) - yCoordOrig * Math.sin( 3.1415 / 2 ) );
				yCoord = Math.round( xCoordOrig * Math.sin( 3.1415 / 2 ) + yCoordOrig * Math.cos( 3.1415 / 2 ) );

				console.log( xCoord + '/' + yCoord );

				this.context.fillRect(
					xCoord * this.blockWidth,
					yCoord * this.blockHeight,
					this.blockWidth - 1,
					this.blockHeight - 1 );
			}
*/

			// Set a timeout interval to update the game state
			this.interval = setInterval( function(){ self.update.apply( self ); }, 107 );
		},

		// Returns a randomly generated tetrominoe
		generateTetromino: function()
		{
			var tetromino = {
//				blocks: this.possibleTetrominoes[ this.rand( 0, this.possibleTetrominoes.length ) ],
				blocks: this.possibleTetrominoes[1],
				color: this.tetrominoColors[ this.rand( 0, this.tetrominoColors.length ) ],

				// Returns a copy of this tetromino
				clone: function()
				{
					var clone = {};
					clone.color = this.color;

					// Deep copy the tetromino coordinates (cause JS copies arrays by reference)
					clone.blocks = Array( 4 );
					for( var n = 0; n < this.blocks.length; ++n )
					{
						clone.blocks[n] = [ this.blocks[n][0], this.blocks[n][1] ];
					}
					clone.clone = this.clone;

					return clone;
				}
			};

			// Position the new tetromino above the board
			// All tetrominoes spawn in 2 usually hidden rows at the top of the playfield.
			// They are placed in the center of these rows, rounding to the left. 

			return tetromino
		},

		// The method called to update the game
		update: function()
		{
			// If the game hasn't been initialized, or is paused, return
			if( ! this.initialized || this.paused )
				return;

			// Get the current time and calculate the amount of time that has elapsed since our last update
			var time = timer.getCurrentTime();
			this.elapsedTime += ( time - this.currentTime );

			if( this.tetrominoLockCountdownRunning )
			{
				// If the lock countdown is running
				// Increment the lock countdown and see if the lock countdown has expired
				this.tetrominoLockCounter += this.elapsedTime;

				if( this.tetrominoLockCounter >= this.tetrominoLockTimeout )
				{
					this.lockCurrentTetromino();
				}
			}
			else if( this.elapsedTime >= this.step )
			{
				// If the elapsed time is greater than the current step value
				// Reset the elapsedtime
				this.elapsedTime = 0;

				// If the lock countdown isn't running for the current tetromino
				if( ! this.tetrominoLockCountdownRunning )
				{
					// Make sure we can move the current tetromino down
					var clone = this.currentTetromino.clone();
					for( var n = 0; n < clone.blocks.length; ++n )
					{
						clone.blocks[n][1] += 1;
					}

					if( this.isValidTetrominoPosition( clone ) )
						this.currentTetromino = clone;
				
					// Check to see if we need to start the lock countdown for the current tetromino
					if( this.hasCurrentTetrominoLanded() )
					{
						// Start the lock countdown
						this.tetrominoLockCounter = 0;
						this.tetrominoLockCountdownRunning = true;
					}
				}
			}

			// Set the current time
			this.currentTime = time;

			// Render our playing field
			this.render();
		},

		// Renders the playfield
		render: function()
		{
			// Clear the canvas
			this.canvasElem.width = this.width;
			this.canvasElem.height = this.height;

			// Draw a rectangle on our canvas
			this.context.fillStyle = this.bgcolor;
			this.context.fillRect( 0, 0, this.width, this.height );

			// Foreach position on the board, render a block if there is one in 
			// that position
			for( var x = 0; x < this.board.length; ++x )
			{
				for( var y = 0; y < this.board[x].length; ++y )
				{
					if( typeof this.board[x][y] === 'string' )
					{
						this.context.fillStyle = this.board[x][y];
						this.context.strokeStyle = this.board[x][y];

						this.context.fillRect(
							x * this.blockWidth,
							y * this.blockHeight,
							this.blockWidth - 1,
							this.blockHeight - 1 );
					}

				}
			}

			// Render the current tetrominoe
			this.context.fillStyle = this.currentTetromino.color;
			this.context.strokeStyle = this.currentTetromino.color;

			for( var n = 0; n < this.currentTetromino.blocks.length; ++n )
			{
				var x = this.currentTetromino.blocks[n][0];
				var y = this.currentTetromino.blocks[n][1];

				this.context.fillRect(
					x * this.blockWidth,
					y * this.blockHeight,
					this.blockWidth - 1,
					this.blockHeight - 1 );
			}
		},

		// Adds the current tetromino to the board, sets the next tetromino as the current tetromino
		// and spawns a new next tetromino
		lockCurrentTetromino: function()
		{
			for( var n = 0; n < this.currentTetromino.blocks.length; ++n )
			{
				var x = this.currentTetromino.blocks[n][0];
				var y = this.currentTetromino.blocks[n][1];
				console.log( x, y );
				this.board[x][y] = this.currentTetromino.color;
			}

			this.currentTetromino = this.nextTetromino;
			this.nextTetromino = this.generateTetromino();

			// Unlock the current tetromino
			this.tetrominoLockCountdownRunning = false;
			this.tetrominoLockCounter = 0;
		},

		// Returns true if the tetromino is in a valid position
		isValidTetrominoPosition: function( tetromino )
		{
			if( ! tetromino )
				return false;

			// The return value, default to true
			var returnValue = true;

			// The right and left most positions of the tetromino
			var rightMostCoord = 0;
			var leftMostCoord = this.board.length;

			// The lowest point on the tetromino
			var lowestCoord = 0;

			// Foreach block in the tetromino
			for( var n = 0; n < tetromino.blocks.length; ++n )
			{
				// Get shortcuts to the coords
				var x = tetromino.blocks[n][0];
				var y = tetromino.blocks[n][1];

				// Update the right most, left most, and lowest coords if we need to
				if( x > rightMostCoord )
					rightMostCoord = x;

				if( x < leftMostCoord )
					leftMostCoord = x;

				if( y > lowestCoord )
					lowestCoord = y;

				// If the block is occupying a position that already has a block on it
				if( x >= 0 && x < this.board.length - 1 && y >= 0 && y < this.board[x].length - 1 && typeof this.board[x][y] === 'string' )
				{
					returnValue = false;
				}
			}

			// If the tetromino isn't off of the board
			if( returnValue )
			{
				// Make sure the tetromino is still on the board
				if( rightMostCoord >= this.board.length )
				{
					returnValue = false;
				}

				if( leftMostCoord < 0 )
				{
					returnValue = false;
				}

				if( lowestCoord > this.board[0].length - 1 )
				{
					returnValue = false;
				}
			}

			// Return our return value
			return returnValue;
		},

		// Returns true if the tetromino is at it's lowest possible position
		hasCurrentTetrominoLanded: function()
		{
			// The return value
			var returnValue = false;

			// For each block in the current tetromino
			for( var n = 0; ( n < this.currentTetromino.blocks.length ) && ( ! returnValue ); ++n )
			{
				// If the current block is at the bottom of the playing field
				if( this.currentTetromino.blocks[n][1] === this.board[ this.currentTetromino.blocks[n][0] ].length - 1 )
				{
					returnValue = true;
				}
				else
				{
					// Otherwise, see if the current block is resting on another block
					var x = this.currentTetromino.blocks[n][0];
					var y = this.currentTetromino.blocks[n][1];

					if( x < this.board.length - 1 && ( y < this.board[x].length - 1 ) && typeof this.board[x][y + 1] === 'string' )
					{
						returnValue = true;
					}
				}
			}

			return returnValue;
		},

		// Called when the user wants to move the current tetromino right
		onTetrominoRight: function()
		{
			// Clone the current tetromino
			var clone = this.currentTetromino.clone();

			// Move the clone right
			for( var n = 0; n < clone.blocks.length; ++n )
			{
				clone.blocks[n][0] += 1;
			}

			// Verify that the current tetromino is in a valid position
			if( this.isValidTetrominoPosition( clone ) )
			{
				this.currentTetromino = clone;

				// Reset the lock countdown
				this.tetrominoLockCounter = 0;

				// If we need to start the lock countdown, do so
				if( this.hasCurrentTetrominoLanded() )
				{
					this.tetrominoLockCountdownRunning = true;
				}

			}

			// Update and render the playing field
			this.update();
			this.render();
		},

		// Called when the user wants to move the current tetromino right
		onTetrominoLeft: function()
		{
			// Clone the current tetromino
			var clone = this.currentTetromino.clone();

			// Move the clone left
			for( var n = 0; n < clone.blocks.length; ++n )
			{
				clone.blocks[n][0] -= 1;
			}

			// Verify that the current tetromino is in a valid position
			if( this.isValidTetrominoPosition( clone ) )
			{
				this.currentTetromino = clone;

				// Reset the lock countdown
				this.tetrominoLockCounter = 0;

				// If we need to start the lock countdown, do so
				if( this.hasCurrentTetrominoLanded() )
				{
					this.tetrominoLockCountdownRunning = true;
				}
			}

			// Update and render the playing field
			this.update();
			this.render();
		},

		debug: function()
		{
			for( var n = 0; n < this.board.length; ++n )
			{
				console.log( this.board[n], this.board[n].length );
			}
		},

		// Pauses the game
		togglePause: function()
		{
			if( ! this.paused )
				this.paused = true;
			else
				this.paused = false;
		},

		// A function so I don't have to remember not to have a comma at the end (thanks IE!)
		commaBait: function(){}
	};

	return tetrisGame;
} )

// Define the controller that will use the tetris service
app.controller( 'tetrisController', [ '$scope', 'tetrisGame', function( $scope, tetrisGame )
{
	// Create our tetris game
	$scope.tetrisGame = tetrisGame;
	$scope.tetrisGame.initialize( 'tetris' );

	// Called when a key press is detected
	$scope.onKeyPress = function( $event )
	{
		if( ! $event )
			return;

		// Switch based on the keycode in the event
		switch( $event.keyCode )
		{
			// The left arrow
			case 37:
				tetrisGame.onTetrominoLeft();
				break;

			// The right arrow
			case 39:
				tetrisGame.onTetrominoRight();
				break;

			// The down arrow
			case 40:
				break;

			// Handle keys that don't have a keycode
			case 0:
				switch( $event.charCode )
				{
					// The S key, shows debug
					case 115:
						tetrisGame.debug();
						break;

					// The space bar (pause)
					case 32:
						tetrisGame.togglePause();
						break;

					// The D key (rotate counter clockwise)
					case 100:
						break;

					// The F key (rotate clockwise)
					case 102:
						break;
				}
				break;
		}
	}

} ] );

</script>

</head>

<body ng-controller="tetrisController" ng-keypress="onKeyPress( $event )">

	<canvas id="tetris" height="400" width="225">
		Sorry, but your browser doesn't support HTML5 :(
	</canvas>

	<div>Lock counter: {{tetrisGame.tetrominoLockCounter}}</div>
	<div>Step: {{tetrisGame.step}}</div>
	<div>elapsedTime: {{tetrisGame.elapsedTime}}</div>

</body>

</html>