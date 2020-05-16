/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Coinche implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * coinche.js
 *
 * Coinche user interface script
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
	return declare('bgagame.coinche', ebg.core.gamegui, {
		constructor: function() {
			this.cardwidth = 72
			this.cardheight = 104
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
			// Private information: player has belote ?
			this.beloteInfo = {
				// Belote card 1 (queen or king trump)
				cardId1: gamedatas.belote_card_id_1,
				// Belote card 2 (queen or king trump)
				cardId2: gamedatas.belote_card_id_2,
				// Belote has been declared already ?
				declared: gamedatas.belote_declared
			}
			// Does this player want to declare the belote ?
			this.wantToDeclareBelote = null

			// Current trump
			this.currentTrump = null

			// List of bubbles timeout
			this.playerBubbles = {}

			// Update information about player bid
			this.updatePlayerBid(false)

			// Player hand
			this.playerHand = new ebg.stock() // new stock object for hand
			this.playerHand.create(this, $('myHand'), this.cardwidth, this.cardheight)
			this.playerHand.setSelectionMode(1)
			this.playerHand.setSelectionAppearance('class')
			this.playerHand.centerItems = true

			this.playerHand.image_items_per_row = 13 // 13 images per row
			// Create cards types:
			for (var color = 1; color <= 4; color++) {
				for (var value = 7; value <= 14; value++) {
					// Build card type id
					var cardTypeId = this.getCardUniqueId(color, value)
					this.playerHand.addItemType(
						cardTypeId,
						cardTypeId,
						g_gamethemeurl + 'img/cards-french.png',
						cardTypeId
					)
				}
			}

			// Observe click on player's hand cards
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
			this.connectClass(
				'.bidPanel__btn--coinche',
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
				var playerId = card.location_arg
				this.playCardOnTable(playerId, color, value, card.id)
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

			if (gamedatas.gamestate.name == 'playerTurn') {
				this.currentTrump = gamedatas.trumpColor
			}
			this.updateCardsWeights()

			// Setup game notifications to handle (see "setupNotifications" method below)
			this.setupNotifications()
		},

		///////////////////////////////////////////////////
		//// Game & client states

		onEnteringState: function(stateName, args) {
			var isBidPanelVisible = false
			var isCoinchePanelVisible = false

			switch (stateName) {
				case 'playerBid':
					if (this.isCurrentPlayerActive()) {
						isBidPanelVisible = true
					}
					isCoinchePanelVisible = true
					break
			}

			if (isBidPanelVisible) {
				dojo.query('.bidPanel').addClass('bidPanel--visible')
				this.scrollBidPanelValues(0)
			} else {
				dojo.query('.bidPanel').removeClass('bidPanel--visible')
			}

			if (isCoinchePanelVisible) {
				dojo
					.query('.playerTables__coinche-btn')
					.addClass('playerTables__coinche-btn--visible')
			} else {
				dojo
					.query('.playerTables__coinche-btn')
					.removeClass('playerTables__coinche-btn--visible')
			}

			// Highlight active player
			dojo
				.query('.playerTables__table')
				.removeClass('playerTables__table--active')
			if (args.active_player) {
				dojo
					.query('.playerTables__table--id--' + args.active_player)
					.addClass('playerTables__table--active')
			}

			// Auto play card if one is selected
			if (stateName === 'playerTurn' && this.isCurrentPlayerActive()) {
				this.playSelectedCard()
			}
		},

		onLeavingState: function(stateName) {},

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
			}
		},

		///////////////////////////////////////////////////
		//// Utility methods

		// Get card unique identifier based on its color and value
		getCardUniqueId: function(color, value) {
			return (color - 1) * 13 + (value - 2)
		},

		playCardOnTable: function(playerId, color, value, cardId) {
			// playerId => direction
			dojo.place(
				this.format_block('jstpl_cardontable', {
					x: this.cardwidth * (value - 2),
					y: this.cardheight * (color - 1),
					player_id: playerId
				}),
				'playerTables__card--' + playerId
			)

			if (playerId != this.player_id) {
				// Some opponent played a card
				// Move card from player panel
				this.placeOnObject(
					'cardontable_' + playerId,
					'overall_player_board_' + playerId
				)
			} else {
				// You played a card. If it exists in your hand, move card from there and remove
				// corresponding item
				if ($('myHand_item_' + cardId)) {
					this.placeOnObject('cardontable_' + playerId, 'myHand_item_' + cardId)
					this.playerHand.removeFromStockById(cardId)
				}
			}

			// In any case: move it to its final destination
			this.slideToObject(
				'cardontable_' + playerId,
				'playerTables__card--' + playerId
			).play()
		},

		// Add item in player's status (on table)
		updatePlayerStatus: function(playerId, html) {
			var target = dojo.query(
				'.playerTables__table--id--' + playerId + ' .playerTables__status'
			)[0]
			while (target.childElementCount >= 5) {
				target.removeChild(target.children[0])
			}
			dojo.place(html, target, 'append')
		},

		// Update a players's bid info
		updatePlayerBidInfo: function(data) {
			if (!data.player_id) {
				return
			}
			this.updatePlayerStatus(
				data.player_id,
				this.format_block('jstpl_playerbid', data)
			)
			this.showPlayerBubble(
				data.player_id,
				this.format_block('jstpl_playerbid', data)
			)
		},

		// Update a players's pass info
		updatePlayerPassInfo: function(data) {
			this.updatePlayerStatus(
				data.player_id,
				this.format_block('jstpl_playerpass', data)
			)
			this.showPlayerBubble(
				data.player_id,
				this.format_block('jstpl_playerpass', data)
			)
		},

		showPlayerBubble: function(playerId, html, duration) {
			var target = dojo.query(
				'.playerTables__table--id--' + playerId + ' .playerTables__bubble'
			)[0]
			target.innerHTML = ''
			if (typeof html == 'string') {
				html = dojo.create('span', { innerHTML: html })
			}
			dojo.place(html, target, 'append')
			if (this.playerBubbles[playerId]) {
				clearTimeout(this.playerBubbles[playerId].timeoutHandle)
			}
			target.classList.add('playerTables__bubble--visible')
			var me = this
			this.playerBubbles[playerId] = {
				timeoutHandle: setTimeout(function() {
					me.hidePlayerBubble(playerId)
				}, duration || 3000)
			}
		},

		hidePlayerBubble: function(playerId) {
			var target = dojo.query(
				'.playerTables__table--id--' + playerId + ' .playerTables__bubble'
			)[0]
			target.classList.remove('playerTables__bubble--visible')
		},

		// Clear all players bid/pass info
		clearPlayerStatuses: function() {
			dojo.query('.playerTables__status').innerHTML('')
		},

		// Update global bid info
		updateBidInfo: function(data) {
			// Hide all counter markers
			dojo
				.query('.playerTables__table__counterMarker')
				.removeClass('playerTables__table__counterMarker--visible')

			if (!(data.bid > 0) || !data.bidPlayerDisplay) {
				// Hide bid panel
				dojo.query('.currentBidInfo').removeClass('currentBidInfo--visible')
				return
			}

			// Show bid info
			dojo.query('.currentBidInfo').addClass('currentBidInfo--visible')
			dojo.place(
				this.format_block('jstpl_currentbidinfo', data),
				dojo.query('.currentBidInfo__wrapper')[0],
				'replace'
			)

			// Update bid panel buttons
			dojo.query('.bidPanel__btn--value').forEach(function(el) {
				var value = +el.getAttribute('data-value')
				if (!data.bid || data.bid < +value) {
					el.classList.remove('bidPanel__btn--hidden')
				} else {
					el.classList.add('bidPanel__btn--hidden')
				}
			})

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

		updateFirstPlayer: function(playerId) {
			dojo
				.query('.playerTables__table')
				.removeClass('playerTables__table--first')
			dojo
				.query('.playerTables__table--id--' + playerId)
				.addClass('playerTables__table--first')
		},

		updatePlayerBid: function(clearValue) {
			if (clearValue) {
				this.playerBid = {
					color: null,
					value: null
				}
			}
			dojo.query('.bidPanel__btn').removeClass('bidPanel__btn--selected')
			if (this.playerBid.value) {
				dojo
					.query(
						'.bidPanel__btn--value[data-value="' + this.playerBid.value + '"]'
					)
					.addClass('bidPanel__btn--selected')
			}
			if (this.playerBid.color) {
				dojo
					.query(
						'.bidPanel__btn--color[data-color="' + this.playerBid.color + '"]'
					)
					.addClass('bidPanel__btn--selected')
			}
		},

		/* This enable to inject translatable styled things to logs or action bar */
		/* @Override */
		format_string_recursive: function(log, args) {
			try {
				if (log && args && !args.processed) {
					args.processed = true

					// Representation of the color of a card
					if (args.color_symbol !== undefined) {
						args.color_symbol = dojo.string.substitute(
							'<span class="card-color-icon--size16 card-color-icon card-color-icon--${color_symbol}"></span>',
							{ color_symbol: args.color_symbol }
						)
					}

					// Representation of the color of a card (by name)
					if (args.color_name !== undefined) {
						args.color_name = dojo.string.substitute(
							'<span class="card-color-icon--size16 card-color-icon card-color-icon--${color_name}"></span>',
							{ color_name: args.color_name }
						)
					}

					// Representation of a bid balue
					if (args.bid_value !== undefined) {
						args.bid_value = dojo.string.substitute(
							'<bold class="bid-value">${bid_value}</bold>',
							{ bid_value: args.bid_value }
						)
					}

					// Trick count : invisible marker to remove logs later
					if (args.trick_count !== undefined) {
						args.trick_count_value = args.trick_count
						args.trick_count = dojo.string.substitute(
							'<span class="trickCountLog" data-value="${trick_count}"></span>',
							{ trick_count: args.trick_count }
						)
					}
				}
			} catch (e) {
				console.error(log, args, 'Exception thrown', e.stack)
			}
			return this.inherited(arguments)
		},

		// Update cards weights based on current trumpColor
		updateCardsWeights: function() {
			var weights = []
			for (var col = 1; col <= 4; col++)
				for (var value = 7; value <= 14; value++) {
					var cardValId = this.getCardUniqueId(col, value)
					weights[cardValId] = this.getCardWeight(col, value)
				}
			this.playerHand.changeItemsWeight(weights)
		},

		getCardWeight: function(col, value) {
			var map = {
				7: 1,
				8: 2,
				9: 3,
				10: 7,
				11: 4,
				12: 5,
				13: 6,
				14: 8
			}
			if (col == this.currentTrump || this.currentTrump == 5) {
				map = {
					7: 1,
					8: 2,
					9: 7,
					10: 5,
					11: 8,
					12: 3,
					13: 4,
					14: 6
				}
			}
			var baseValue = map[value] || 1
			return col * 10 + baseValue
		},

		clearOldTricksLogs: function(maxTrick) {
			dojo.query('.trickCountLog').forEach(function(el) {
				var trickNumber = el.getAttribute('data-value')
				if (trickNumber > maxTrick) {
					return
				}
				var logEl = el.parentNode.parentNode
				if (!logEl.classList.contains('log')) {
					return
				}
				logEl.remove()
			})
		},

		playSelectedCard: function() {
			var cardId = this.selectedCardId
			if (!cardId) {
				return
			}
			if (
				!(this.beloteInfo.declared == 1) &&
				this.wantToDeclareBelote === null &&
				(cardId == this.beloteInfo.cardId1 || cardId == this.beloteInfo.cardId2)
			) {
				this.multipleChoiceDialog(
					_('Do you want to declare the belote (+20pts)'),
					['Oui', 'Non'],
					dojo.hitch(this, function(choice) {
						this.wantToDeclareBelote = choice == '0'
						this.playSelectedCard()
					})
				)
				return
			}
			this.ajaxcall(
				'/' + this.game_name + '/' + this.game_name + '/' + 'playCard.html',
				{
					id: cardId,
					lock: true,
					belote: this.wantToDeclareBelote
				},
				this,
				function(result) {},
				function(is_error) {}
			)
			this.selectedCardId = null
			this.playerHand.unselectAll()
		},

		scrollBidPanelValues: function(where) {
			var tickSize = 54
			var listEl = dojo.query('.bidPanel__values-list')[0]
			var currentScroll = listEl.scrollLeft
			if (where == 'right') {
				currentScroll += tickSize
			} else if (where == 'left') {
				currentScroll -= tickSize
			} else {
				currentScroll = where
			}
			currentScroll = tickSize * Math.ceil(currentScroll / tickSize)
			listEl.scrollLeft = currentScroll
		},

		///////////////////////////////////////////////////
		//// Player's action

		onPlayerHandSelectionChanged: function() {
			var items = this.playerHand.getSelectedItems()

			if (items.length <= 0) {
				this.selectedCardId = null
				return
			}

			this.selectedCardId = items[0].id

			if (this.checkAction('playCard', true)) {
				this.playSelectedCard()
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
			e.preventDefault()
			e.stopPropagation()
			var target = e.currentTarget
			if (target.classList.contains('bidPanel__btn--value-left')) {
				this.scrollBidPanelValues('left')
				return
			}
			if (target.classList.contains('bidPanel__btn--value-right')) {
				this.scrollBidPanelValues('right')
				return
			}

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
			this.updatePlayerBid(false)
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
			dojo.subscribe('newScores', this, 'notif_newScores')
			dojo.subscribe('newHand', this, 'notif_newHand')
			dojo.subscribe('firstPlayerChange', this, 'notif_firstPlayerChange')
			dojo.subscribe('updateBid', this, 'notif_updateBid')
			dojo.subscribe('updateBidPass', this, 'notif_updateBidPass')
			dojo.subscribe('updateBidCoinche', this, 'notif_updateBidCoinche')
			dojo.subscribe('allPassNoBid', this, 'notif_allPassNoBid')
			this.notifqueue.setSynchronous('allPassNoBid', 2000)
			dojo.subscribe('allPassWithBid', this, 'notif_allPassWithBid')
			this.notifqueue.setSynchronous('allPassWithBid', 2000)
			dojo.subscribe('playCard', this, 'notif_playCard')
			dojo.subscribe('trickWin', this, 'notif_trickWin')
			this.notifqueue.setSynchronous('trickWin', 1000)
			dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer')
			dojo.subscribe('belote', this, 'notif_belote')
			dojo.subscribe('sayBelote', this, 'notif_sayBelote')
		},

		notif_newScores: function(notif) {
			// Update players' scores
			for (var playerId in notif.args.newScores) {
				this.scoreCtrl[playerId].toValue(notif.args.newScores[playerId])
			}
		},

		notif_newHand: function(notif) {
			this.currentTrump = null
			this.updateCardsWeights()
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
			this.clearPlayerStatuses()
			this.beloteInfo = {
				cardId1: null,
				cardId2: null,
				declared: false
			}
			this.wantToDeclareBelote = null

			// Reactive all bidPanel buttons
			dojo.query('.bidPanel__btn--value').removeClass('bidPanel__btn--hidden')

			// Remove "coinche" playerTables
			dojo.query('.playerTables').removeClass('playerTables--coinched')
		},

		notif_allPassWithBid: function(notif) {
			this.selectedCardId = null
			this.clearPlayerStatuses()
			this.updatePlayerStatus(
				notif.args.player_id,
				this.format_block('jstpl_playerbid', notif.args)
			)
			this.currentTrump = notif.args.trumpColor
			this.updateCardsWeights()
			this.clearOldTricksLogs(99)
			this.showPlayerBubble(
				notif.args.player_id,
				_("Let's go with") + this.format_block('jstpl_playerbid', notif.args)
			)
		},

		notif_allPassNoBid: function(notif) {
			this.currentTrump = null
			this.clearPlayerStatuses()
			this.clearOldTricksLogs(99)
		},

		notif_firstPlayerChange: function(notif) {
			this.updateFirstPlayer(notif.args.player_id)
			this.showPlayerBubble(notif.args.player_id, _("I'm starting"))
		},

		notif_updateBidCoinche: function(notif) {
			this.showPlayerBubble(
				notif.args.player_id,
				'<span color="red">' + _('Countered !') + '</span>'
			)
			this.clearPlayerStatuses()
			dojo.query('.playerTables').addClass('playerTables--coinched')
		},

		notif_updateBidPass: function(notif) {
			this.updatePlayerPassInfo(notif.args)
		},

		notif_updateBid: function(notif) {
			if (notif.log != '') {
				this.updatePlayerBidInfo(notif.args)
			}
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
			this.clearOldTricksLogs(notif.args.trick_count_value - 1)
		},

		notif_giveAllCardsToPlayer: function(notif) {
			// Move all cards on table to given table, then destroy them
			var winner_id = notif.args.player_id
			for (var player_id in this.gamedatas.players) {
				var anim = this.slideToObject(
					'cardontable_' + player_id,
					'playerTables__card--' + winner_id
				)
				dojo.connect(anim, 'onEnd', function(node) {
					dojo.destroy(node)
				})
				anim.play()
			}
		},

		// Private information, this player has the belote
		notif_belote: function(notif) {
			this.beloteInfo = {
				cardId1: notif.args.belote_card_id_1,
				cardId2: notif.args.belote_card_id_2,
				declared: notif.args.belote_declared
			}
		},

		// Public information, a player is saying "belote" or "rebelote"
		notif_sayBelote: function(notif) {
			this.showPlayerBubble(notif.args.player_id, notif.args.belote_text + ' !')
		}
	})
})
