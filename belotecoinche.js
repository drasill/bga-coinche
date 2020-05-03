/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BeloteCoinche implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * belotecoinche.js
 *
 * BeloteCoinche user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
	'dojo',
	'dojo/_base/declare',
	'ebg/core/gamegui',
	'ebg/counter',
	'ebg/stock'
], function(dojo, declare) {
	return declare('bgagame.belotecoinche', ebg.core.gamegui, {
		constructor: function() {
			console.log('belotecoinche constructor')

			this.cardwidth = 72
			this.cardheight = 96
		},

		/**
		 * setup:
		 * This method must set up the game user interface according to current game situation specified
		 * in parameters.
		 * The method is called each time the game interface is displayed to a player, ie:
		 * _ when the game starts
		 * _ when a player refreshes the game page (F5)
		 * "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
		 */
		setup: function(gamedatas) {
			console.log('Starting game setup')

			// Setting up player boards
			for (var player_id in gamedatas.players) {
				var player = gamedatas.players[player_id]
			}

			this.playerBid = {
				color: null,
				value: null
			}
			this.updatePlayerBid()

			this.playerHand = new ebg.stock() // new stock object for hand
			this.playerHand.create(this, $('myhand'), this.cardwidth, this.cardheight)

			this.playerHand.image_items_per_row = 13 // 13 images per row
			// Create cards types:
			for (var color = 1; color <= 4; color++) {
				for (var value = 2; value <= 14; value++) {
					// Build card type id
					var card_type_id = this.getCardUniqueId(color, value)
					this.playerHand.addItemType(
						card_type_id,
						card_type_id,
						g_gamethemeurl + 'img/cards.jpg',
						card_type_id
					)
				}
			}

			dojo.connect(
				this.playerHand,
				'onChangeSelection',
				this,
				'onPlayerHandSelectionChanged'
			)

			this.connectClass('bidPanel__btn', 'onclick', 'onBidPanelBtnClick')

			// Cards in player's hand
			for (var i in this.gamedatas.hand) {
				var card = this.gamedatas.hand[i]
				var color = card.type
				var value = card.type_arg
				this.playerHand.addToStockWithId(
					this.getCardUniqueId(color, value),
					card.id
				)
			}

			// Cards played on table
			for (i in this.gamedatas.cardsontable) {
				var card = this.gamedatas.cardsontable[i]
				var color = card.type
				var value = card.type_arg
				var player_id = card.location_arg
				this.playCardOnTable(player_id, color, value, card.id)
			}

			this.updateBidInfo({
				trumpColor: gamedatas.trumpColor,
				trumpColorDisplay: gamedatas.trumpColorDisplay,
				bid: gamedatas.bid,
				bidPlayer: gamedatas.bidPlayer,
				bidPlayerDisplay: gamedatas.bidPlayerDisplay
			})

			// Setup game notifications to handle (see "setupNotifications" method below)
			this.setupNotifications()

			console.log('Ending game setup')
		},

		///////////////////////////////////////////////////
		//// Game & client states

		// onEnteringState: this method is called each time we are entering into a new game state.
		//                  You can use this method to perform some user interface changes at this moment.
		//
		onEnteringState: function(stateName, args) {
			console.log('Entering state: ' + stateName)

			switch (stateName) {
				/* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */

				case 'dummmy':
					break
			}
		},

		// onLeavingState: this method is called each time we are leaving a game state.
		//                 You can use this method to perform some user interface changes at this moment.
		//
		onLeavingState: function(stateName) {
			console.log('Leaving state: ' + stateName)

			switch (stateName) {
				/* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */

				case 'dummmy':
					break
			}
		},

		// onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
		//                        action status bar (ie: the HTML links in the status bar).
		//
		onUpdateActionButtons: function(stateName, args) {
			console.log('onUpdateActionButtons: ' + stateName)
			if (this.isCurrentPlayerActive()) {
				switch (stateName) {
					case 'playerBid':
						this.addActionButton(
							'pass_button',
							_('Pass'),
							'onPlayerPass',
							null,
							false,
							'gray'
						)
						break
				}
			}
		},

		///////////////////////////////////////////////////
		//// Utility methods

		// Get card unique identifier based on its color and value
		getCardUniqueId: function(color, value) {
			return (color - 1) * 13 + (value - 2)
		},

		playCardOnTable: function(player_id, color, value, card_id) {
			// player_id => direction
			dojo.place(
				this.format_block('jstpl_cardontable', {
					x: this.cardwidth * (value - 2),
					y: this.cardheight * (color - 1),
					player_id: player_id
				}),
				'playerTables__card--' + player_id
			)

			if (player_id != this.player_id) {
				// Some opponent played a card
				// Move card from player panel
				this.placeOnObject(
					'cardontable_' + player_id,
					'overall_player_board_' + player_id
				)
			} else {
				// You played a card. If it exists in your hand, move card from there and remove
				// corresponding item

				console.log(card_id)
				if ($('myhand_item_' + card_id)) {
					this.placeOnObject(
						'cardontable_' + player_id,
						'myhand_item_' + card_id
					)
					this.playerHand.removeFromStockById(card_id)
				}
			}

			// In any case: move it to its final destination
			this.slideToObject(
				'cardontable_' + player_id,
				'playerTables__card--' + player_id
			).play()
		},

		updateBidInfo(data) {
			console.log(data)
			if (!data.bid || !data.bidPlayerDisplay) {
				return
			}
			dojo.place(
				this.format_block('jstpl_currentbidinfo', data),
				'currentBidInfo',
				'replace'
			)
		},

		updatePlayerBid(clearValue = false) {
			if (clearValue) {
				this.playerBid = {
					color: null,
					value: null
				}
			}
			dojo.query('.bidPanel__btn').removeClass('bgabutton_blue')
			if (this.playerBid.value) {
				dojo
					.query(
						'.bidPanel__btn--value[data-value="' + this.playerBid.value + '"]'
					)
					.addClass('bgabutton_blue')
			}
			if (this.playerBid.color) {
				dojo
					.query(
						'.bidPanel__btn--color[data-color="' + this.playerBid.color + '"]'
					)
					.addClass('bgabutton_blue')
			}
		},

		///////////////////////////////////////////////////
		//// Player's action

		/**
		 *
		 * Here, you are defining methods to handle player's action (ex: results of mouse click on
		 * game objects).
		 *
		 * Most of the time, these methods:
		 * _ check the action is possible at this game state.
		 * _ make a call to the game server
		 *
		 */
		onPlayerHandSelectionChanged: function() {
			var items = this.playerHand.getSelectedItems()

			if (items.length > 0) {
				var action = 'playCard'
				if (this.checkAction(action, true)) {
					// Can play a card
					var card_id = items[0].id
					this.ajaxcall(
						'/' +
							this.game_name +
							'/' +
							this.game_name +
							'/' +
							action +
							'.html',
						{
							id: card_id,
							lock: true
						},
						this,
						function(result) {},
						function(is_error) {}
					)

					this.playerHand.unselectAll()
				} else {
					this.playerHand.unselectAll()
				}
			}
		},

		onPlayerBid: function() {
			if (this.checkAction('bid')) {
				this.ajaxcall(
					'/' + this.game_name + '/' + this.game_name + '/' + 'bid' + '.html',
					{
						value: 100,
						color: 2,
						lock: true
					},
					this,
					function(result) {},
					function(is_error) {}
				)
			}
		},

		onPlayerPass: function() {
			if (this.checkAction('pass')) {
				this.ajaxcall(
					'/' + this.game_name + '/' + this.game_name + '/' + 'pass' + '.html',
					{
						lock: true
					},
					this,
					function(result) {},
					function(is_error) {}
				)
			}
		},

		onBidPanelBtnClick: function(e) {
			const target = e.currentTarget
			console.log(e, target)
			if (target.classList.contains('bidPanel__btn--pass')) {
				this.updatePlayerBid(true)
				this.onPlayerPass()
				return
			}
			if (target.classList.contains('bidPanel__btn--color')) {
				this.playerBid.color = target.getAttribute('data-color')
			}
			if (target.classList.contains('bidPanel__btn--value')) {
				this.playerBid.value = target.getAttribute('data-value')
			}
			this.updatePlayerBid()
			if (this.playerBid.value && this.playerBid.color) {
				this.ajaxcall(
					'/' + this.game_name + '/' + this.game_name + '/' + 'bid' + '.html',
					{
						value: this.playerBid.value,
						color: this.playerBid.color,
						lock: true
					},
					this,
					function(result) {
						this.updatePlayerBid(true)
					},
					function(is_error) {
						this.updatePlayerBid(true)
					}
				)
			}
		},

		/* Example:
        
        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/belotecoinche/belotecoinche/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

		///////////////////////////////////////////////////
		//// Reaction to cometD notifications

		/*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your belotecoinche.game.php file.
        
        */
		setupNotifications: function() {
			dojo.subscribe('newHand', this, 'notif_newHand')
			dojo.subscribe('updateBid', this, 'notif_updateBid')
			dojo.subscribe('playCard', this, 'notif_playCard')
			dojo.subscribe('trickWin', this, 'notif_trickWin')
			this.notifqueue.setSynchronous('trickWin', 1000)
			dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer')
		},

		notif_newHand: function(notif) {
			// We received a new full hand of 8 cards.
			this.playerHand.removeAll()

			for (var i in notif.args.cards) {
				var card = notif.args.cards[i]
				var color = card.type
				var value = card.type_arg
				this.playerHand.addToStockWithId(
					this.getCardUniqueId(color, value),
					card.id
				)
			}
		},

		notif_updateBid: function(notif) {
			this.updateBidInfo(notif.args)
		},

		notif_playCard: function(notif) {
			// Play a card on the table
			this.playCardOnTable(
				notif.args.player_id,
				notif.args.color,
				notif.args.value,
				notif.args.card_id
			)
		},

		notif_trickWin: function(notif) {
			// We do nothing here (just wait in order players can view the 4 cards played before they're gone.
		},

		notif_giveAllCardsToPlayer: function(notif) {
			// Move all cards on table to given table, then destroy them
			var winner_id = notif.args.player_id
			for (var player_id in this.gamedatas.players) {
				var anim = this.slideToObject(
					'cardontable_' + player_id,
					'overall_player_board_' + winner_id
				)
				dojo.connect(anim, 'onEnd', function(node) {
					dojo.destroy(node)
				})
				anim.play()
			}
		}
	})
})
