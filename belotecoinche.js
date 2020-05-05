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
			this.playerHand.create(this, $('myHand'), this.cardwidth, this.cardheight)
			this.playerHand.setSelectionAppearance('class')
			this.playerHand.centerItems = true

			this.playerHand.image_items_per_row = 13 // 13 images per row
			// Create cards types:
			for (var color = 1; color <= 4; color++) {
				for (var value = 2; value <= 14; value++) {
					// Build card type id
					var cardTypeId = this.getCardUniqueId(color, value)
					this.playerHand.addItemType(
						cardTypeId,
						cardTypeId,
						g_gamethemeurl + 'img/cards.jpg',
						cardTypeId
					)
				}
			}

			dojo.connect(
				this.playerHand,
				'onChangeSelection',
				this,
				'onPlayerHandSelectionChanged'
			)

			// Buttons of bidPanel
			this.connectClass('bidPanel__btn', 'onclick', 'onBidPanelBtnClick')

			// Coinche Button
			this.connectClass(
				'.playerTables__coinche-btn > a',
				'onclick',
				'onCoincheBtnClick'
			)

			// First Player
			this.updateFirstPlayer(gamedatas.firstPlayer)

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

			// Bid informations
			this.updateBidInfo({
				trumpColor: gamedatas.trumpColor,
				trumpColorDisplay: gamedatas.trumpColorDisplay,
				bid: gamedatas.bid,
				bidPlayer: gamedatas.bidPlayer,
				bidPlayerDisplay: gamedatas.bidPlayerDisplay,
				countered: gamedatas.countered,
				counteringPlayer: gamedatas.counteringPlayer,
				counteringPlayerDisplay: gamedatas.counteringPlayerDisplay
			})

			// Setup game notifications to handle (see "setupNotifications" method below)
			this.setupNotifications()
		},

		///////////////////////////////////////////////////
		//// Game & client states

		onEnteringState: function(stateName, args) {
			console.log('Entering state: ' + stateName, args)

			let isBidPanelVisible = false
			let isCoinchePanelVisible = false

			switch (stateName) {
				case 'playerBid':
					if (this.isCurrentPlayerActive()) {
						isBidPanelVisible = true
					}
					isCoinchePanelVisible = true
					break
			}

			dojo
				.query('.bidPanel')
				[isBidPanelVisible ? 'addClass' : 'removeClass']('bidPanel--visible')
			dojo
				.query('.playerTables__coinche-btn')
				[isCoinchePanelVisible ? 'addClass' : 'removeClass'](
					'playerTables__coinche-btn--visible'
				)
		},

		onLeavingState: function(stateName) {
			console.log('Leaving state: ' + stateName)
		},

		onUpdateActionButtons: function(stateName, args) {
			if (stateName === 'playerBid') {
				if (this.isCurrentPlayerActive()) {
					this.addActionButton(
						'pass_button',
						_('Pass'),
						'onPlayerPass',
						null,
						false,
						'gray'
					)
				}
				this.addActionButton(
					'coinche_button',
					_('Counter'),
					'onPlayerCoinche',
					null,
					false,
					'red'
				)
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
				if ($('myHand_item_' + card_id)) {
					this.placeOnObject(
						'cardontable_' + player_id,
						'myHand_item_' + card_id
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

		// Update a players's bid info
		updatePlayerBidInfo(data) {
			if (!data.player_id) {
				return
			}
			const target = dojo.query(
				'.playerTables__table--id--' + data.player_id + ' .playerTables__card'
			)[0]
			console.log('updatePlayerBidInfo', target, data)
			dojo.place(this.format_block('jstpl_playerbid', data), target, 'append')
		},

		// Update a players's pass info
		updatePlayerPassInfo(data) {
			const target = dojo.query(
				'.playerTables__table--id--' + data.player_id + ' .playerTables__card'
			)[0]
			dojo.place(this.format_block('jstpl_playerpass', data), target, 'append')
		},

		// Clear all players bid/pass info
		clearPlayerBidItems() {
			dojo.query('.playerTables__bid-item').remove()
		},

		// Update global bid info
		updateBidInfo(data) {
			// Hide all counter markers
			dojo
				.query('.playerTables__table__counterMarker')
				.removeClass('playerTables__table__counterMarker--visible')

			if (!(data.bid > 0) || !data.bidPlayerDisplay) {
				// Hide bid panel
				dojo.query('.currentBidInfo').removeClass('currentBidInfo--visible')
				return
			}

			// Show bidPanel
			dojo.query('.currentBidInfo').addClass('currentBidInfo--visible')

			// Update bidPanel content
			dojo.place(
				this.format_block('jstpl_currentbidinfo', data),
				dojo.query('.currentBidInfo__wrapper')[0],
				'replace'
			)

			// Activate countered marker of player
			if (data.countered && data.counteringPlayer) {
				dojo
					.query(
						'.playerTables__table--id--' +
							data.counteringPlayer +
							' .playerTables__table__counterMarker'
					)
					.addClass('playerTables__table__counterMarker--visible')
			}
		},

		updateFirstPlayer(playerId) {
			dojo
				.query('.playerTables__table')
				.removeClass('playerTables__table--first')
			dojo
				.query('.playerTables__table--id--' + playerId)
				.addClass('playerTables__table--first')
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

		onPlayerCoinche: function() {
			if (this.checkPossibleActions('coinche')) {
				this.ajaxcall(
					'/' +
						this.game_name +
						'/' +
						this.game_name +
						'/' +
						'coinche' +
						'.html',
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

		onCoincheBtnClick: function(e) {
			this.onPlayerCoinche()
		},

		///////////////////////////////////////////////////
		//// Reaction to cometD notifications

		setupNotifications: function() {
			dojo.subscribe('newHand', this, 'notif_newHand')
			dojo.subscribe('firstPlayerChange', this, 'notif_firstPlayerChange')
			dojo.subscribe('updateBid', this, 'notif_updateBid')
			dojo.subscribe('updateBidPass', this, 'notif_updateBidPass')
			dojo.subscribe('updateBidCoinche', this, 'notif_updateBidCoinche')
			dojo.subscribe('allPassNoBid', this, 'notif_allPassNoBid')
			dojo.subscribe('allPassWithBid', this, 'notif_allPassWithBid')
			dojo.subscribe('playCard', this, 'notif_playCard')
			dojo.subscribe('trickWin', this, 'notif_trickWin')
			this.notifqueue.setSynchronous('trickWin', 1000)
			dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer')
		},

		notif_newHand: function(notif) {
			console.log('notif_newHand')
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
			this.clearPlayerBidItems()
		},

		notif_allPassWithBid: function(notif) {
			console.log('notif_allPassWithBid')
			this.clearPlayerBidItems()
		},

		notif_allPassNoBid: function(notif) {
			console.log('notif_allPassNoBid')
			this.clearPlayerBidItems()
		},

		notif_firstPlayerChange: function(notif) {
			console.log('notif_firstPlayerChange')
			this.updateFirstPlayer(notif.args.player_id)
		},

		notif_updateBidCoinche: function(notif) {
			console.log('notif_updateBidCoinche')
			this.clearPlayerBidItems()
		},

		notif_updateBidPass: function(notif) {
			console.log('notif_updateBidPass')
			this.updatePlayerPassInfo(notif.args)
		},

		notif_updateBid: function(notif) {
			console.log('notif_updateBid')
			this.updatePlayerBidInfo(notif.args)
			this.updateBidInfo(notif.args)
		},

		notif_playCard: function(notif) {
			console.log('notif_playCard')
			// Play a card on the table
			this.playCardOnTable(
				notif.args.player_id,
				notif.args.color,
				notif.args.value,
				notif.args.card_id
			)
		},

		notif_trickWin: function(notif) {
			console.log('notif_trickWin')
			// We do nothing here (just wait in order players can view the 4 cards played before they're gone.
		},

		notif_giveAllCardsToPlayer: function(notif) {
			console.log('notif_giveAllCardsToPlayer')
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
