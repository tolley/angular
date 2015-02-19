<!DOCTYPE html>
<html ng-app="myApp">

<head>

<meta name="viewport" content="width=device-width, height=device-height, user-scalable=no">

<title>Angular.js and HTML5 version of Tetris</title>

<script src="/js/hammer.js"></script>
<script src="/js/angular.js"></script>

<script type="text/javascript">
"use strict";

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

// Define our main app
var app = angular.module( 'myApp', [] );

// Define the directive that will allow us to display the board
app.directive( 'tetris', [ '$document', function( $document )
{
	return {
		restrict: 'E',
		replace: 'true',
		transclude: true,
		templateUrl: 'tetris_template.html',

		link: function( scope, elem, attrs )
		{
			// Get the canvas element
			var canvas = elem.find( 'canvas' )[0];

			scope.tetrisGame.canvasElem = canvas;
			scope.tetrisGame.context = canvas.getContext( '2d' );

			// If the main canvas was found, initialize our game
			if( scope.tetrisGame.canvasElem )
			{
				scope.tetrisGame.initialize( scope );

				// Apply the mobile events using hammer.js to the canvas
				var mobileHammer = new Hammer( scope.tetrisGame.canvasElem, {} );

				// Enable vertical swiping
				mobileHammer.get( 'swipe' ).set( { direction: Hammer.DIRECTION_VERTICAL } );

				// Plug into the tap event
				mobileHammer.on( 'tap', function( event )
				{
					event.preventDefault();
					scope.tetrisGame.onTap( event );
				} );

				// Plug into the pan event, move the current tetromino left or right depending on the
				// distance and direction of the pan
				mobileHammer.on( 'pan', function( event )
				{
					event.preventDefault();
					scope.tetrisGame.onPan( event );
				} );

				// Plug into the pan start event, to start tracking the finger movement X delta
				mobileHammer.on( 'panstart', function( event )
				{
					event.preventDefault();
					scope.tetrisGame.onPanStart();
				} );

				// Plug into the pan end event, to stop tracking the finger movement X delta
				mobileHammer.on( 'panend', function( event )
				{
					event.preventDefault();
					scope.tetrisGame.onPanEnd();
				} );

				// Move the current tetromino to the lowest possible row
				mobileHammer.on( 'swipeup', function( event )
				{
					event.preventDefault();
					scope.tetrisGame.dropCurrentTetromino();
				} );

				// Move the current tetromino one row down
				mobileHammer.on( 'swipedown', function( event )
				{
					event.preventDefault();
					scope.tetrisGame.onTetrominoDown();
				} );
			}
		}
	};
} ] );

// Define our tetris playing app
app.factory( 'tetrisGame', function()
{
	var tetrisGame = {
		// A variable to keep track of whether or not the game has been initialized
		initialized: false,

		// The canvas element and it's context that will display our playing field
		canvasElem: false,
		context: false,

		// A flag indicating if the game is over or not
		bGameOver: false,

		// Returns a random number between min and max inclusive
		rand: function( min, max )
		{
			return Math.floor( Math.random() * ( max + min ) );
		},

		// A method to initialize this instance of the game
		initialize: function( scope )
		{
			// If the game has already been initialized, return
			if( this.initialized )
				return;

			// Set a reference to the scope
			this.scope = scope;

			var self = this;

			// We need multiple height/width variables, one for the main playing area, and one for totals
			this.canvasHeight = 450;
			this.canvasWidth  = 315

			this.fieldHeight = 450;
			this.fieldWidth = 225;

			// The height and width of the areas to render the swapped and next tetrominos
			// There are two different sections, but they will have the same dimensions
			this.sidePanelWidth = 90;
			this.sidePanelHeight = 90;

			// Calculate the width and height of a block
			this.blockWidth = this.fieldWidth / 10;
			this.blockHeight = this.fieldHeight / 20;

			// The speed (in milliseconds) for steps between block falls
			this.step = 700;

			// The level that the player is on.  This is used to update the step speed every 10 lines
			this.level = 0;

			// The counter that tells us when to move onto the next tetromino
			this.tetrominoLockCounter = 0;

			// The maximum time (in milliseconds) to wait to lock the current tetromino
			this.tetrominoLockTimeout = 700;

			// A flag to indicate the lock countdown is running
			this.tetrominoLockCountdownRunning = false;

			// The game's timer
			this.currentTime = timer.getCurrentTime(),
			this.elapsedTime = 0;

			// A variable that will keep track of the total number of lines the player has made
			this.total_num_lines = 0;

			// A flag to keep track of whether or not the current tetromino has been swapped
			// A tetromino can only be swapped once
			this.swapped = false;

			// The variable that stores the swapped tetromino
			this.swappedTetromino = false;

			// Generate the actual board (initially populate it with zeros)
			this.board = Array();
			this.boardWidth = 10;
			this.boardHeight = 20;

			// The absolute value of the distance the user must pan before we move the current tetromino
			this.panThreshold = 30;

			// Stores the direction of the current pan
			this.currentPanDirection = false;

			// Keeps track of the current pan distance
			this.currentPanDistance = 0;

			// A variable to use to keep track of the previous distance when onPan is called cause
			// hammer's event.distance keeps the total distance since the start of the pan, but we 
			// need the distance panned since the last time onPan was called.
			this.previousDistance = 0;

			for( var x = 0; x < this.boardWidth; ++x )
			{
				var row = Array();
				for( var y = 0; y < this.boardHeight; ++y )
					row.push( 0 );

				this.board.push( row );
			}

			// A list of all possible blocks
			this.possibleTetrominoes = Array(
				Array( [3,-1], [4,-1], [5,-1], [6,-1] ), // i shaped block
				Array( [5,-2], [4,-2], [3,-1], [3,-2] ), // j shaped block
				Array( [5,-1], [4,-1], [3,-1], [3,-2] ), // l shaped block
				Array( [5,-1], [5,-2], [4,-1], [4,-2] ), // o shaped block
				Array( [3,-1], [4,-1], [4,-2], [5,-1] ), // z shaped block
				Array( [4,-2], [3,-1], [4,-1], [5,-1] ), // t shaped block
				Array( [3,-2], [4,-1], [4,-2], [5,-1] )  // s shaped block
			);

			// A list of pivot points for each of the above tetromino block groups
			this.pivotPoints = Array(
				[5,	 -1],   // i shaped block
				[4,  -2],   // j shaped block
				[4,  -1],   // l shaped block 
				[4.5,-1.5], // o shaped block
				[4,  -1],   // z shaped block 
				[4,  -1],   // t shaped block 
				[4,  -1]    // s shaped block 
			);

			// A list of all possible tetromino colors
			this.tetrominoColors = Array(
				'#FF0000',
				'#00FF00',
				'#38B48B',
				'#F68D2E',
				'#2572FF',
				'#C1FFFF'
			);

			// The current tetromino that is falling
			this.currentTetromino = this.generateTetromino();

			// The next tetromino that will fall
			this.nextTetromino = this.generateTetromino();

			// An array of effects that augment the board
			this.effects = Array();

			// The board's background color
			this.bgcolor = '#000000';

			// Clear our canvas
			this.canvasElem.width = this.canvasWidth;
			this.canvasElem.height = this.canvasHeight;

			// Draw a rectangle on our canvas
			this.context.fillStyle = this.bgcolor;
			this.context.fillRect( 0, 0, this.fieldWidth, this.fieldHeight );

			// A variable used to pause the game
			this.paused = false;

			// Set the initialized flag
			this.initialized = true;

			// Set a timeout interval to update the game state
			this.interval = setInterval( function()
			{
				self.scope.$apply(
					function(){ self.update.apply( self ); }
				);
			}, 107 );
		},

		// Returns a randomly generated tetrominoe
		generateTetromino: function()
		{
			// Choose a random tetromino
			var rand = this.rand( 0, this.possibleTetrominoes.length );

			// Store the original blocks and pivot in case we need to reset
			var originalBlocks = this.possibleTetrominoes[ rand ];
			var originalPivot = this.pivotPoints[ rand ];

			var tetromino = {
				index: rand,
				blocks: this.possibleTetrominoes[ rand ],
				pivot: this.pivotPoints[ rand ],
				color: this.tetrominoColors[ this.rand( 0, this.tetrominoColors.length ) ],

				// Resets the coordinates in this tetromino back to their default
				reset: function()
				{
					this.blocks = originalBlocks;
					this.pivot = originalPivot;
				},

				// Returns a copy of this tetromino
				clone: function()
				{
					var clone = {};
					clone.color = this.color;
					clone.index = this.index;

					// Deep copy the pivot point coordinates (cause JS copies arrays by reference)
					clone.pivot = Array( 2 );
					clone.pivot[0] = this.pivot[0];
					clone.pivot[1] = this.pivot[1];

					// Deep copy the tetromino coordinates (cause JS copies arrays by reference)
					clone.blocks = Array( 4 );
					for( var n = 0; n < this.blocks.length; ++n )
					{
						clone.blocks[n] = [ this.blocks[n][0], this.blocks[n][1] ];
					}
					clone.clone = this.clone;

					clone.reset = this.reset;

					return clone;
				}
			};

			return tetromino
		},

		// The method called to update the game
		update: function()
		{
			// If the game hasn't been initialized, or if it's game over, return
			if( ! this.initialized || this.bGameOver )
				return;

			// Get the current time and calculate the amount of time that has elapsed since our last update
			var time = timer.getCurrentTime();
			this.elapsedTime += ( time - this.currentTime );

			// If the game is not paused, update the game state
			if( ! this.paused )
			{
				// If we have any effects, update them
				if( this.effects.length > 0 )
				{
					for( var n = 0; n < this.effects.length; ++n )
					{
						this.effects[n].update( time - this.currentTime );
					}
				}
				else
				{
					// If the lock countdown is running
					if( this.tetrominoLockCountdownRunning )
					{
						// Increment the lock countdown and see if the lock countdown has expired
						this.tetrominoLockCounter += ( time - this.currentTime );

						if( this.tetrominoLockCounter >= this.tetrominoLockTimeout )
						{
							this.lockCurrentTetromino();
						}
						else if( ! this.isTetrominoOnBoard( this.currentTetromino ) )
						{
							// Verify that the tetromino is on the board
							// If the current tetromino can't find a place on the board, this means
							// the player has lost and the game is over
							this.gameOver();
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

							// Move the pivot down one block
							clone.pivot[1] += 1;

							if( this.isValidTetrominoPosition( clone ) )
								this.currentTetromino = clone;
						
							// Check to see if we need to start the lock countdown for the current tetromino
							if( this.hasTetrominoLanded( this.currentTetromino ) )
							{
								// Start the lock countdown
								this.tetrominoLockCounter = 0;
								this.tetrominoLockCountdownRunning = true;
							}
						}
					}
				}
			}

			// Set the current time
			this.currentTime = time;

			// Render our playing field if the game hasn't ended
			if( ! this.bGameOver )
				this.render();
		},

		// Renders the playfield
		render: function()
		{
			// Clear the entire canvas
			this.canvasElem.width = this.canvasElem.width;
			this.canvasElem.height = this.canvasElem.height;

			// Translate the origin of the drawing context into position and render the number of lines the player has made
			this.context.save();
			this.context.translate( this.fieldWidth, ( this.sidePanelHeight * 2 + ( this.sidePanelHeight / 2 ) ) );
			this.renderNumLines();
			this.context.restore();

			// Translate the origin of the drawing context into position and render the next tetromino display
			this.context.save();
			this.context.translate( this.fieldWidth, 0 );
			this.renderNextTetromino();
			this.context.restore();

			// Translate the origin of the drawing context into position and render the swapped tetromino display
			this.context.save();
			this.context.translate( this.fieldWidth, this.sidePanelHeight );
			this.renderSwappedTetromino();
			this.context.restore();

			// Translate the origin of the drawing context into position and render the pause button
			this.context.save();
			this.context.translate( this.fieldWidth, ( this.sidePanelHeight * 2 ) );
			this.renderPauseButton();
			this.context.restore();

			// If the game is not paused
			if( ! this.paused )
			{
				// Draw a rectangle on our canvas
				this.context.fillStyle = this.bgcolor;
				this.context.fillRect( 0, 0, this.fieldWidth, this.fieldHeight );

				// Render the boarder for our tetris field
				this.context.strokeStyle = '#FFFFFF';
				this.context.strokeRect( 0, 0, this.canvasWidth, this.canvasHeight );

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

				// Render the ghost tetromino on the board
				this.renderGhostTetromino();

				// Render the current tetromino
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

				// If we have any effects
				if( this.effects.length > 0 )
				{
					for( var n = this.effects.length - 1; n >= 0;--n )
					{
						// Render the effect and then check to see if it is complete
						this.effects[n].render();

						// If the effect has completed, remove it from the effects array
						if( this.effects[n].isComplete() )
							this.effects.splice( n, 1 );
					}
				}
			}
			else
			{
				// Draw a rectangle on our canvas
				this.context.save();
				this.context.fillStyle = this.bgcolor;
				this.context.fillRect( 0, 0, this.fieldWidth, this.fieldHeight );
				this.context.restore();

				// Render the word paused to the main playing field
				this.showStatusMessage( 'Paused' );
			}
		},

		// Renders the swapped tetromino display
		renderSwappedTetromino: function()
		{
			// Render the label for this section and a border around it
			this.context.strokeStyle = '#FFFFFF';
			this.context.strokeRect( 0, 0, this.sidePanelWidth, this.sidePanelHeight );

			// Draw a black rectangle on our canvas
			this.context.fillStyle = this.bgcolor;
			this.context.fillRect( 0, 0, this.sidePanelWidth, this.sidePanelHeight );

			// Scale the canvas so the swapped tetromino will fit properly
			this.context.scale( 0.7, 0.7 );

			// If there is a swapped tetromino, render it
			if( this.swappedTetromino )
			{
				// Render each block on the swapped tetromino
				this.context.fillStyle = this.swappedTetromino.color;
				this.context.strokeStyle = this.swappedTetromino.color;

				for( var n = 0; n < this.swappedTetromino.blocks.length; ++n )
				{
					var x = this.swappedTetromino.blocks[n][0];
					var y = this.swappedTetromino.blocks[n][1];

					this.context.fillRect(
						( x - 2 ) * this.blockWidth,
						( y * -1 ) * this.blockHeight,
						this.blockWidth - 1,
						this.blockHeight - 1
					);
				}
			}
		},

		// Renders the pause button
		renderPauseButton: function()
		{
			// Set up the color of the pause button (grey background with black text)
			this.context.fillStyle = '#EEEEEE';

			// Render the pause button rectangle
			this.context.fillRect( 0, 0, this.sidePanelWidth, 30 );

			// Render the pause button rectangle outline
			this.context.strokeStyle = '#000000';
			this.context.strokeRect( 0, 0, this.sidePanelWidth, 30 );

			// Render the word "pause" or "unpause" depending on whether or not the game is paused
			var msg = 'pause';
			var left = 15;
			if( this.paused )
			{
				msg = 'unpause';
				left = 3;
			}

			// Set up the canvas for the text
			this.context.fillStyle = '#000000';
			this.context.font = '20px Comic Sans';

			// Render the actual text
			this.context.fillText( msg, left, 20 );
		},

		// Renders the next tetromino display
		renderNextTetromino: function()
		{
			// Render the label for this section and a border around it
			this.context.strokeStyle = '#FFFFFF';
			this.context.strokeRect( 0, 0, this.sidePanelWidth, this.sidePanelHeight );

			// Draw a black rectangle on our canvas
			this.context.fillStyle = this.bgcolor;
			this.context.fillRect( 0, 0, this.sidePanelWidth, this.sidePanelHeight );

			// Render each block on the next tetromino
			this.context.fillStyle = this.nextTetromino.color;
			this.context.strokeStyle = this.nextTetromino.color;

			// Scale the canvas so the next tetromino will fit properly
			this.context.scale( 0.7, 0.7 );

			for( var n = 0; n < this.nextTetromino.blocks.length; ++n )
			{
				var x = this.nextTetromino.blocks[n][0];
				var y = this.nextTetromino.blocks[n][1];

				this.context.fillRect(
					( x - 2 ) * this.blockWidth,
					( y * -1 ) * this.blockHeight,
					this.blockWidth - 1,
					this.blockHeight - 1 );
			}
		},

		// Renders the preview (ghost) tetromino onto the board so the user can see where the current
		// will land
		renderGhostTetromino: function()
		{
			// If the current tetromino has already landed, don't render the ghost
			if( this.hasTetrominoLanded( this.currentTetromino ) )
				return;

			// Clone the current tetromino
			var ghost = this.currentTetromino.clone();

			// Move the ghost down until it can't go any further down
			do
			{
				// Foreach block in the tetromino, move it down by one
				for( var n = 0; n < ghost.blocks.length; ++n )
				{
					ghost.blocks[n][1] += 1;
				}
			}
			while( ! this.hasTetrominoLanded( ghost ) );

			// Determine whether the ghost is intersecting with the current tetromino
			var bIntersecting = false;
			for( var n = 0; ( n < this.currentTetromino.blocks.length && ! bIntersecting ); ++n )
			{
				for( var m = 0; ( m < ghost.blocks.length && ! bIntersecting ); ++m )
				{
					if( this.currentTetromino.blocks[n][0] == ghost.blocks[m][0] && 
						this.currentTetromino.blocks[n][1] == ghost.blocks[m][1] )
							bIntersecting = true;
				}
			}

			// If the current tetromino is not sharing a block with the ghost
			if( ! bIntersecting )
			{
				// Render the ghost in it's lowest possible position
				this.context.fillStyle = 'rgba( 238, 238, 238, 0.3 )';

				for( var n = 0; n < ghost.blocks.length; ++n )
				{
					// Render the block as a half transparent block
					this.context.strokeStyle = 'rgba( 238, 238, 238, 0.5 )';
					this.context.fillRect(
						ghost.blocks[n][0] * this.blockWidth,
						ghost.blocks[n][1] * this.blockHeight,
						this.blockWidth - 1,
						this.blockHeight - 1
					);

					// Render a white line around the block
					this.context.strokeStyle = '#EEEEEE';
					this.context.strokeRect(
						ghost.blocks[n][0] * this.blockWidth,
						ghost.blocks[n][1] * this.blockHeight,
						this.blockWidth - 1,
						this.blockHeight - 1
					);
				}
			}
		},

		// Renders the current number of lines the player has made
		renderNumLines: function()
		{
			// Set up the canvas for the text
			this.context.fillStyle = '#000000';
			this.context.font = '15px Comic Sans';

			var msg = '# Lines: ' + this.total_num_lines;  	

			// Render the actual text
			this.context.fillText( msg, 5, 5 );
		},

		// Adds the current tetromino to the board, sets the next tetromino as the current tetromino
		// and spawns a new next tetromino
		lockCurrentTetromino: function()
		{
			for( var n = 0; n < this.currentTetromino.blocks.length; ++n )
			{
				var x = this.currentTetromino.blocks[n][0];
				var y = this.currentTetromino.blocks[n][1];
				this.board[x][y] = this.currentTetromino.color;
			}

			this.currentTetromino = this.nextTetromino;
			this.nextTetromino = this.generateTetromino();

			// Unlock the current tetromino
			this.tetrominoLockCountdownRunning = false;
			this.tetrominoLockCounter = 0;

			// Set the swapped flag to false
			this.swapped = false;

			// Check the board to see if any lines where made
			this.doLineCheck();
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
				if( x >= 0 && x < this.board.length && y >= 0 && y < this.board[x].length && typeof this.board[x][y] === 'string' )
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
		hasTetrominoLanded: function( tetromino )
		{
			// The return value
			var returnValue = false;

			// For each block in the current tetromino
			for( var n = 0; ( n < tetromino.blocks.length ) && ( ! returnValue ); ++n )
			{
				// If the current block is at the bottom of the playing field
				if( tetromino.blocks[n][1] === this.board[ tetromino.blocks[n][0] ].length - 1 )
				{
					returnValue = true;
				}
				else
				{
					// Otherwise, see if the current block is resting on another block
					var x = tetromino.blocks[n][0];
					var y = tetromino.blocks[n][1];

					if( x < this.board.length && y < this.board[x].length && typeof this.board[x][y + 1] === 'string' )
					{
						returnValue = true;
					}
				}
			}

			return returnValue;
		},

		// Called when the user wants to rotate the current tetromino clockwise
		onTetrominoClockwise: function()
		{
			// If the game hasn't been initialized, or is paused, return
			if( ! this.initialized || this.paused )
				return;

			// Clone the current tetromino
			var clone = this.currentTetromino.clone();

			// Rotate the clone
			for( var n = 0; n < clone.blocks.length; ++n )
			{
				// Move the current block back to the origin (0,0) using the pivot point
				clone.blocks[n][0] -= clone.pivot[0];
				clone.blocks[n][1] -= clone.pivot[1];

				// Rotate the current block
				var tempX = clone.blocks[n][0];
				clone.blocks[n][0] = -1 * clone.blocks[n][1];
				clone.blocks[n][1] = tempX;

				// Move the current block back into it's position using the pivot point
				clone.blocks[n][0] = Math.round( clone.blocks[n][0] + clone.pivot[0] );
				clone.blocks[n][1] = Math.round( clone.blocks[n][1] + clone.pivot[1] );
			}

			// See if the tetromino has space on the board
			var validRotation = this.validateTetrominoRotation( clone );
			if( validRotation !== false )
				this.currentTetromino = validRotation;

			// If we need to start the lock countdown, do so
			if( this.hasTetrominoLanded( this.currentTetromino ) )
			{
				this.tetrominoLockCountdownRunning = true;
			}
			else
			{
				// Otherwise, make sure the current tetromino is unlocked
				this.tetrominoLockCountdownRunning = false;
				this.tetrominoLockCounter = 0;
			}

			// Update and render the playing field
			this.update();
			this.render();
		},

		// Called when the user wants to rotate the current tetromino counter clockwise
		onTetrominoCounterClockwise: function()
		{
			// If the game hasn't been initialized, or is paused, or we have effects, return
			if( ! this.initialized || this.paused || this.effects.length )
				return;

			// Clone the current tetromino
			var clone = this.currentTetromino.clone();

			// Rotate the clone
			for( var n = 0; n < clone.blocks.length; ++n )
			{
				// Move the current block back to the origin (0,0) using the pivot point
				clone.blocks[n][0] -= clone.pivot[0];
				clone.blocks[n][1] -= clone.pivot[1];

				// Rotate the current block
				var tempX = clone.blocks[n][0];
				clone.blocks[n][0] = clone.blocks[n][1];
				clone.blocks[n][1] = -1 * tempX;

				// Move the current block back into it's position using the pivot point
				clone.blocks[n][0] = Math.round( clone.blocks[n][0] + clone.pivot[0] );
				clone.blocks[n][1] = Math.round( clone.blocks[n][1] + clone.pivot[1] );
			}

			// See if the tetromino has space on the board
			var validRotation = this.validateTetrominoRotation( clone );
			if( validRotation !== false )
				this.currentTetromino = validRotation;

			// If we need to start the lock countdown, do so
			if( this.hasTetrominoLanded( this.currentTetromino ) )
			{
				this.tetrominoLockCountdownRunning = true;
			}
			else
			{
				// Otherwise, make sure the current tetromino is unlocked
				this.tetrominoLockCountdownRunning = false;
				this.tetrominoLockCounter = 0;
			}

			// Update and render the playing field
			this.update();
			this.render();
		},

		// Returns true if clone is in a valid position on the board, or can be moved into a valid position
		// If it needs to be moved, it will be moved automatically (cause JS passes things by reference)
		validateTetrominoRotation: function( clone )
		{
			// The return value
			var returnValue = false;

			// If the rotated clone isn't in a valid position on the board
			if( ! this.isValidTetrominoPosition( clone ) )
			{
				// A list of all the translations we need to try to find 
				// a valid position for the rotated tetromino
				var validTranslations = Array(
					[-1,  0],
					[1,   0],
					[-2,  0],
					[2,   0],
					[-1, -1],
					[1,   1]
				);

				// Try translating the clone by each of the valid translations until we find one 
				// that results in a valid position
				for( var n = 0; ( n < validTranslations.length ) && ( ! returnValue ); ++n )
				{
					// Translate the clone
					for( var m = 0; m < clone.blocks.length; ++m )
					{
						clone.blocks[m][0] += validTranslations[n][0];
						clone.blocks[m][1] += validTranslations[n][1];
					}

					clone.pivot[0] += validTranslations[n][0];
					clone.pivot[1] += validTranslations[n][1];

					// If the translation results in a valid board position
					if( this.isValidTetrominoPosition( clone ) )
					{
						returnValue = clone;
					}
					else
					{
						// Otherwise, move the clone back to it's original position
						for( var m = 0; m < clone.blocks.length; ++m )
						{
							clone.blocks[m][0] -= validTranslations[n][0];
							clone.blocks[m][1] -= validTranslations[n][1];
						}

						clone.pivot[0] -= validTranslations[n][0];
						clone.pivot[1] -= validTranslations[n][1];
					}
				}
			}
			else
			{
				// Otherwise, we can return the clone
				returnValue = clone;
			}

			return returnValue;
		},

		// Called when the user taps or clicks on the tetris field
		// event is the event object as passed from hammer
		onTap: function( event )
		{
			// See if the tap was in the swapped tetromino display
			// The swapped tetromino area starts at 225/90 and is 90x90
			if( ( event.center.x >= this.fieldWidth && event.center.x <= ( this.fieldWidth + this.sidePanelWidth ) ) && 
				( event.center.y >= this.sidePanelHeight && event.center.y <= ( this.sidePanelHeight + this.sidePanelHeight ) ) )
			{
				this.scope.tetrisGame.swap();
			}			
			// Otherwise, determine if the tap was on the pause/unpause button
			else if( ( event.center.x >= this.fieldWidth && event.center.x <= ( this.fieldWidth + this.sidePanelWidth ) ) && 
					( event.center.y >= ( this.sidePanelHeight + this.sidePanelHeight ) && 
						event.center.y <= ( this.sidePanelHeight + this.sidePanelHeight + 30 ) ) )
			{
				this.togglePause();
			}
			else
			{
				// Otherwise, determine whether the tap was on the left or right of the canvas
				var canvasMiddle = parseInt( this.scope.tetrisGame.canvasWidth / 2 );

				// If the tap occured on the left side, rotate the tetromino counter clockwise,
				// otherwise, the tap was on the right half and we need to rotate clockwise
				if( event.center.x <= canvasMiddle )
					this.scope.tetrisGame.onTetrominoCounterClockwise();
				else
					this.scope.tetrisGame.onTetrominoClockwise();
			}
		},

		// Called when the user starts a pan
		onPanStart: function()
		{
			// Reset the current pan direction and distance
			this.currentPanDirection = false;
			this.currentPanDistance = 0;
			this.previousDistance = 0;
		},

		// Called when the user ends the pan
		onPanEnd: function()
		{
			// Reset the current pan direction and distance
			this.currentPanDirection = false;
			this.currentPanDistance = 0;
			this.previousDistance = 0;
		},

		// Called when the user pans (each time the user moves their finger)
		onPan: function( event )
		{
			// If we have a pan that is not horizontal, return
			if( Math.abs( event.deltaY ) > Math.abs( event.deltaX ) )
			{
				return;
			}

			if( event.direction != Hammer.DIRECTION_RIGHT && event.direction != Hammer.DIRECTION_LEFT )
			{
				return;
			}

			// Calculate the distance the pan has moved since the last onPan event
			var currentPanDistance = 0;

			// We have to figure out which one is greater cause the user can switch directions mid pan
			// and the distance will start reducing instead of increasing
			if( event.distance > this.previousDistance )
			{
				currentPanDistance = Math.floor( event.distance ) - this.previousDistance;
			}
			else
			{
				currentPanDistance = this.previousDistance - Math.floor( event.distance );
			}

			// Set the current distance to the previous distance
			this.previousDistance = Math.floor( event.distance );

			// If the current pan direction hasn't been set, or if it's been changed, update our variables
			if( ! this.currentPanDirection || event.direction != this.currentPanDirection )
			{
				this.currentPanDirection = event.direction;
				this.currentPanDistance = currentPanDistance;
			}
			else
			{
				// Otherwise, add the pan distance to our counter
				this.currentPanDistance += currentPanDistance;
			}

			// If the user has panned far enough to move the current tetromino
			if( this.currentPanDistance >= this.panThreshold )
			{
				// Determine how many columns we need to move the current tetromino
				var numColumns = Math.floor( this.currentPanDistance / this.panThreshold );

				// Move the current tetromino based on the direction
				for( var n = 0; n < numColumns; ++n )
				{
					if( this.currentPanDirection == Hammer.DIRECTION_RIGHT )
					{
						this.onTetrominoRight();
					}
					else if( this.currentPanDirection == Hammer.DIRECTION_LEFT )
					{
						this.onTetrominoLeft();
					}

					// Reduce the current pan distance so it does not accumulate (if it does, the tetromino
					// will jump across the field)
					this.currentPanDistance -= this.panThreshold;
				}
			}
		},

		// Called when the user wants to move the current tetromino right
		onTetrominoRight: function()
		{
			// If the game hasn't been initialized, is paused, has ended, or we have any effects, return
			if( ! this.initialized || this.paused || this.bGameOver || this.effects.length > 0 )
				return;

			// Clone the current tetromino
			var clone = this.currentTetromino.clone();

			// Move the clone right
			for( var n = 0; n < clone.blocks.length; ++n )
			{
				clone.blocks[n][0] += 1;
			}

			// Move the pivot point
			clone.pivot[0] += 1;

			// Verify that the current tetromino is in a valid position
			if( this.isValidTetrominoPosition( clone ) )
			{
				this.currentTetromino = clone;

				// Reset the lock countdown
				this.tetrominoLockCounter = 0;

				// If we need to start the lock countdown, do so
				if( this.hasTetrominoLanded( this.currentTetromino ) )
				{
					this.tetrominoLockCountdownRunning = true;
				}
				else
				{
					// Otherwise, unlock the current tetromino
					this.tetrominoLockCountdownRunning = false;
				}

			}

			// Update and render the playing field
			this.update();
			this.render();
		},

		// Called when the user wants to move the current tetromino right
		onTetrominoLeft: function()
		{
			// If the game hasn't been initialized, is paused, has ended, or if we have any effects
			if( ! this.initialized || this.paused || this.bGameOver || this.effects.length > 0 )
				return;

			// Clone the current tetromino
			var clone = this.currentTetromino.clone();

			// Move the clone left
			for( var n = 0; n < clone.blocks.length; ++n )
			{
				clone.blocks[n][0] -= 1;
			}

			// Move the pivot left
			clone.pivot[0] -= 1;

			// Verify that the current tetromino is in a valid position
			if( this.isValidTetrominoPosition( clone ) )
			{
				this.currentTetromino = clone;

				// Reset the lock countdown
				this.tetrominoLockCounter = 0;

				// If we need to start the lock countdown, do so
				if( this.hasTetrominoLanded( this.currentTetromino ) )
				{
					this.tetrominoLockCountdownRunning = true;
				}
				else
				{
					// Otherwise, unlock the current tetromino
					this.tetrominoLockCountdownRunning = false;
				}
			}

			// Update and render the playing field
			this.update();
			this.render();
		},

		// Moves the current tetromino down one row
		onTetrominoDown: function()
		{
			// If the game hasn't been initialized, is paused, has ended, or if we have any effects
			if( ! this.initialized || this.paused || this.bGameOver || this.effects.length > 0 )
				return;

			// Clone the current tetromino
			var clone = this.currentTetromino.clone();

			// Move the clone left
			for( var n = 0; n < clone.blocks.length; ++n )
			{
				clone.blocks[n][1] += 1;
			}

			// Move the pivot left
			clone.pivot[1] += 1;

			// Verify that the current tetromino is in a valid position
			if( this.isValidTetrominoPosition( clone ) )
			{
				this.currentTetromino = clone;

				// Reset the lock countdown
				this.tetrominoLockCounter = 0;

				// If we need to start the lock countdown, do so
				if( this.hasTetrominoLanded( this.currentTetromino ) )
				{
					this.tetrominoLockCountdownRunning = true;
				}
				else
				{
					// Otherwise, unlock the current tetromino
					this.tetrominoLockCountdownRunning = false;
				}
			}

			// Update and render the playing field
			this.update();
			this.render();
		},

		// Sends the current tetromino to it's lowest possible point and locks it
		dropCurrentTetromino: function()
		{
			// If the game hasn't been initialized, or is paused, return
			if( ! this.initialized || this.paused )
				return;

			// While the clone is in a valid position, move it down one row
			do
			{
				// Clone the current tetromino
				var clone = this.currentTetromino.clone();

				// Foreach block in the tetromino, move it down by one
				for( var n = 0; n < clone.blocks.length; ++n )
				{
					clone.blocks[n][1] += 1;
				}

				// Move the pivot down one row
				clone.pivot[1] += 1;

				// If we have a valid tetromino position, make the clone the current tetromino
				if( this.isValidTetrominoPosition( clone ) )
					this.currentTetromino = clone;
			}
			while( ! this.hasTetrominoLanded( this.currentTetromino ) );

			this.lockCurrentTetromino();
		},

		doLineCheck: function()
		{
			// An array to keep track of the row of any lines we've made
			var lines = Array();

			// Foreach row on the board
			for( var y = 0; y < this.board[0].length; ++y )
			{
				// A flag to break the loop when we find an empty cell
				var openCell = false;

				// Foreach column in the current row
				for( var x = 0; ( x < this.board.length && ! openCell ); ++x )
				{
					if( typeof this.board[x][y] !== 'string' )
						openCell = true;
				}

				// If we found a row with no empty cells in it, add it to the lines array
				if( ! openCell )
					lines.push( y );
			}

			// If the player made any lines
			if( lines.length > 0 )
			{
				// For each line
				for( var n = 0; n < lines.length; ++n )
				{
					var y = lines[n];

					// Increment the total number of lines that player has made so far
					this.total_num_lines++;

					// For each cell in the current line
					for( var x = 0; x < this.board.length; ++x )
					{
						// Set the cell value to 0 to mark it as an empty cell
						this.board[x][y] = 0;
					}

					// Create a fade out effect for the current line
					this.generateLineFade( y );
				}

				// Determine whether or not we need to update the level
				if( parseInt( this.total_num_lines / 10 ) > this.level )
				{
					this.level++;

					// Deincrement the step value
					this.step -= 50;
					if( this.step < 100 )
						this.step = 100;
				}
			}
		},

		// Swaps the current tetromino with the one in storage.  If there is none in storage
		// it will be swapped with the next tetromino
		swap: function()
		{
			// If the game hasn't been initialized, or is paused, return
			if( ! this.initialized || this.paused )
				return;

			// If the current tetromino has already been swapped, return, cause you
			// can only swap once per tetromino
			if( this.swapped )
				return;

			// Set the swapped flag to true so we can't swap again until the next tetromino drops
			this.swapped = true;

			// Reset the coords on the current tetromino
			this.currentTetromino.reset();

			// If there is a tetromino that was previously swapped
			if( this.swappedTetromino )
			{
				// Swap the current tetromino with the one in the swap variable
				var tempTetromino = this.currentTetromino;
				this.currentTetromino = this.swappedTetromino;
				this.swappedTetromino = tempTetromino;
			}
			else
			{
				// Otherwise, we can put the current tetromino into the swap space, set
				// the next tetromino as the current, and create a new next
				this.swappedTetromino = this.currentTetromino;
				this.currentTetromino = this.nextTetromino;
				this.nextTetromino = this.generateTetromino();
			}
		},

		// Generates a fade out effect for a given line
		generateLineFade: function( lineNum )
		{
			var blockHeight = this.blockHeight;
			var boardWidth = this.fieldWidth;
			var context = this.context;
			var board = this.board;

			var fadeEffect = {
				lineNumber: lineNum,
				blockHeight: blockHeight,
				boardWidth: boardWidth,
				board: board,

				// The drawing context
				context: context,

				// The alpha value to use for this line
				alpha: 1,

				// Updates the fading line.  elapsedTime is the amount of time that has
				// elapsed since the last update
				update: function( elapsedTime )
				{
					// Reduce the alpha value
					this.alpha -= elapsedTime * 0.001;
					this.alpha = parseFloat( this.alpha.toFixed( 2 ) );

					// If the alpha value is 0 or less
					if( this.alpha <= 0 )
					{
						// Drop each block down a line on the board
						for( var y = this.lineNumber; y > 0; --y)
						{
							for( var x = 0; x < this.board.length; ++x )
							{
								if( typeof this.board[x][y - 1] === 'string' )
								{
									this.board[x][y] = this.board[x][y - 1];
									this.board[x][y - 1] = 0;
								}
							}
						}
					}
				},

				render: function()
				{
					// Only render if the alpha is > zero
					if( this.alpha > 0 )
					{
						this.context.fillStyle = 'rgba( 256, 256, 256, ' + this.alpha + ' )';
						this.context.strokeStyle = 'rgba( 256, 256, 256, ' + this.alpha + ' )';

						this.context.fillRect(
							0,
							this.lineNumber * this.blockHeight,
							this.boardWidth - 1,
							this.blockHeight - 1 );
					}
				},

				// Returns true if this effect has been completed
				isComplete: function()
				{
					// This effect will finish after 1 second (when alpha is zero)
					return this.alpha <= 0;
				}
			};

			this.effects.push( fadeEffect );
		},

		// Returns false if any of the tetromino's blocks aren't on the visible board
		isTetrominoOnBoard: function( tetromino )
		{
			// The return value
			var isOnBoard = true;

			// For each block in the tetromino, see if it's off the visible board
			for( var n = 0; ( n < tetromino.blocks.length && isOnBoard ); ++n )
			{
				if( tetromino.blocks[n][1] < 0 )
					isOnBoard = false;
			}

			return isOnBoard;
		},

		// Pauses or unpauses the game
		togglePause: function()
		{
			if( this.paused && ! this.bGameOver )
			{
				this.paused = false;
			}
			else
			{
				// Set the paused flag to true and render the paused message
				this.paused = true;
				this.showStatusMessage( 'Paused' );
			}
		},

		// Renders a message to the center of the main playing field
		showStatusMessage: function( msg )
		{
			// Save the current state of the drawing context
			this.context.save();

			// Draw a rectangle on our tetris field
			this.context.fillStyle = this.bgcolor;
			this.context.fillRect( 0, 0, this.fieldWidth, this.fieldHeight );

			// Set up the canvas for the text
			this.context.fillStyle = '#FFFFFF';
			this.context.font = '30px Comic Sans';

			// Calculate the center position for the text placement
			var messageStats = this.context.measureText( msg );
			var textLeft = Math.floor( this.fieldWidth / 2 - messageStats.width / 2 );

			this.context.fillText( msg, textLeft, 50 );

			// Reset the state of the drawing context
			this.context.restore();
		},

		// Restarts the game
		restart: function()
		{
			this.initialized = false;
			this.initialize( this.scope );
		},

		// Displays a game over message
		gameOver: function()
		{
			this.bGameOver = true;
			this.paused = true;

			this.showStatusMessage( 'Game Over' );
		}
	};

	return tetrisGame;
} )

// Define the controller that will use the tetris service
app.controller( 'tetrisController', [ '$scope', 'tetrisGame', function( $scope, tetrisGame )
{
	// Add the tetris game to the scope
	$scope.tetrisGame = tetrisGame;

	// Moves the current tetromino left one
	$scope.moveLeft = function()
	{
		tetrisGame.onTetrominoLeft();
	}

	// Moves the current tetromino left one
	$scope.moveRight = function()
	{
		tetrisGame.onTetrominoRight();
	}

	// Pauses/Unpauses the game
	$scope.togglePause = function()
	{
		tetrisGame.togglePause();
	}

	// Rotates the current tetromino counter clockwise
	$scope.rotateCounterClockwise = function()
	{
		tetrisGame.onTetrominoCounterClockwise();
	}

	// Rotates the current tetromino clockwise
	$scope.rotateClockwise = function()
	{
		tetrisGame.onTetrominoClockwise();
	}

	// Swaps the current tetromino 
	$scope.swap = function()
	{
		tetrisGame.swap();
	}

	// Moves the current tetromino down one row
	$scope.moveDown = function()
	{
		tetrisGame.onTetrominoDown();
	}

	// Drops the current tetromino
	$scope.drop = function()
	{
		tetrisGame.dropCurrentTetromino();
	}

	// Restarts the current game
	$scope.restart = function()
	{
		tetrisGame.restart();
	}

	// Set up the UI calls to the tetris game
	$scope.onKeyDown = function( $event )
	{
		if( ! $event )
			return;

		// Switch based on which key was pressed
		switch( $event.which )
		{
			// The space bar, pause the game
			case 32:
				tetrisGame.togglePause();
				break;

			// The D key (rotate counter clockwise)
			case 68:
				tetrisGame.onTetrominoCounterClockwise();
				break;

			// The F key (rotate clockwise)
			case 70:
				tetrisGame.onTetrominoClockwise();
				break;

			// The S key, swap the current tetromino
			case 83:
				tetrisGame.swap();
				break;

			// The down arrow (move the tetromino down one block)
			case 40:
				tetrisGame.onTetrominoDown();
				break;

			// The up arrow (drop the current tetromino as far as it can go)
			case 38:
				tetrisGame.dropCurrentTetromino();
				break;

			// The left arrow
			case 37:
				tetrisGame.onTetrominoLeft();
				break;

			// The right arrow
			case 39:
				tetrisGame.onTetrominoRight();
				break;

			// The q key, restarts the game
			case 81:
				tetrisGame.restart();
				break;
		}
	}
} ] );

</script>

<style>
	div.container div.swapped_canvas_wrapper {
		float: left;
		height: 400px;
	}

	div.container div.main_canvas_wrapper {
		float: left;
		height: 100%;
	}

	canvas.tetrisField {
		float: left;
		margin: 0px 2px 0px 5px;
	}

	div.container div.preview_canvas_wrapper {
		float: left;
		height: 400px;
	}

	div#controls
	{
		padding-left: 10px;
		border: solid 1px #000;
		width: 350px;
		float: right;
	}

	@media (max-width: 480px)
	{
		.mobile_only{ display: none; }
	}

	@media (min-width: 481px) {
		.mobile_only{ display: block; }
	} 
</style>

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