<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BeloteCoinche implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * belotecoinche.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';

class BeloteCoinche extends Table {
	function __construct() {
		parent::__construct();

		self::initGameStateLabels([
			'currentHandType' => 10,
			'trickColor' => 11,
			'trumpColor' => 12,
			'bid' => 13,
			'countered' => 14,
			'passCount' => 15,
			'bidPlayer' => 16,
			'firstPlayer' => 17,
			'counteringPlayer' => 18,
		]);

		$this->cards = self::getNew('module.common.deck');
		$this->cards->init('card');
	}

	protected function getGameName() {
		// Used for translations and stuff. Please do not modify.
		return 'belotecoinche';
	}

	/**
	 *  setupNewGame:
	 *  This method is called only once, when a new game is launched.
	 *  In this method, you must setup the game according to the game rules, so that
	 *  the game is ready to be played.
	 */
	protected function setupNewGame($players, $options = []) {
		// Set the colors of the players with HTML color code
		// The default below is red/green/blue/orange/brown
		// The number of colors defined here must correspond to the maximum number of players allowed for the gams
		$gameinfos = self::getGameinfos();
		$default_colors = $gameinfos['player_colors'];

		// Create players
		// Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
		$sql =
			'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
		$values = [];
		foreach ($players as $playerId => $player) {
			$color = array_shift($default_colors);
			$values[] =
				"('" .
				$playerId .
				"','$color','" .
				$player['player_canal'] .
				"','" .
				addslashes($player['player_name']) .
				"','" .
				addslashes($player['player_avatar']) .
				"')";
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);
		self::reattributeColorsBasedOnPreferences(
			$players,
			$gameinfos['player_colors']
		);
		self::reloadPlayersBasicInfos();

		/************ Start the game initialization *****/
		// Init global values with their initial values

		// Note: hand types: 0 = give 3 cards to player on the left
		//                   1 = give 3 cards to player on the right
		//                   2 = give 3 cards to player opposite
		//                   3 = keep cards
		self::setGameStateInitialValue('currentHandType', 0);

		// Set current trick color to zero (= no trick color)
		self::setGameStateInitialValue('trickColor', 0);

		// Set current trump color to zero (= no trump color)
		self::setGameStateInitialValue('trumpColor', 0);

		// Set current bid
		self::setGameStateInitialValue('bid', 0);

		// 0: non coinché, 1: coinché, 2: surcoinché
		self::setGameStateInitialValue('countered', 0);

		// Count player passes
		self::setGameStateInitialValue('passCount', 0);

		$firstPlayerId = array_rand($players, 1);
		self::setGameStateInitialValue('firstPlayer', $firstPlayerId);

		// Create cards
		$cards = [];
		foreach ($this->colors as $color_id => $color) {
			// spade, heart, diamond, club
			for ($value = 7; $value <= 14; $value++) {
				//  7, 8, 9, 10, J, Q, K, A
				$cards[] = [
					'type' => $color_id,
					'type_arg' => $value,
					'nbr' => 1,
				];
			}
		}
		$this->cards->createCards($cards, 'deck');

		// Shuffle deck
		$this->cards->shuffle('deck');
		// Deal 8 cards to each players
		$players = self::loadPlayersBasicInfos();
		foreach ($players as $playerId => $player) {
			$cards = $this->cards->pickCards(8, 'deck', $playerId);
		}

		// Init game statistics
		// (note: statistics used in this file must be defined in your stats.inc.php file)
		//self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
		//self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

		// TODO: setup the initial game situation here

		// Activate first player (which is in general a good idea :) )
		$this->activeNextPlayer();

		/************ End of the game initialization *****/
	}

	/*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
	protected function getAllDatas() {
		$result = [];

		$current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!

		// Get information about players
		// Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
		$sql = 'SELECT player_id id, player_score score FROM player ';
		$result['players'] = self::getCollectionFromDb($sql);

		// Cards in player hand
		$result['hand'] = $this->cards->getCardsInLocation(
			'hand',
			$current_player_id
		);

		// Cards played on the table
		$result['cardsontable'] = $this->cards->getCardsInLocation(
			'cardsontable'
		);

		$players = self::loadPlayersBasicInfos();
		$trumpColor = self::getGameStateValue('trumpColor');
		$bid = self::getGameStateValue('bid');
		$bidPlayer = self::getGameStateValue('bidPlayer');
		$firstPlayer = self::getGameStateValue('firstPlayer');
		$countered = self::getGameStateValue('countered');
		$counteringPlayer = self::getGameStateValue('counteringPlayer');

		$result['trumpColor'] = $trumpColor;
		$result['trumpColorDisplay'] = $this->colors[$trumpColor]['name'] ?? null;
		$result['bid'] = $bid;
		$result['bidPlayer'] = $bidPlayer;
		$result['bidPlayerDisplay'] = $players[$bidPlayer]['player_name'] ?? null;
		$result['countered'] = $countered;
		$result['counteringPlayer'] = $counteringPlayer;
		$result['counteringPlayerDisplay'] = $players[$counteringPlayer]['player_name'] ?? '';
		$result['firstPlayer'] = $firstPlayer;

		return $result;
	}

	/*
	 * getGameProgression:
	 *
	 * Compute and return the current game progression.
	 * The number returned must be an integer beween 0 (=the game just started) and
	 * 100 (= the game is finished or almost finished).
	 *
	 * This method is called each time we are in a game state with the "updateGameProgression" property set to true
	 * (see states.inc.php)
	 */
	function getGameProgression() {
		// TODO: compute and return the game progression

		return 0;
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Utility functions
	////////////

	/**
	 * Return players => direction (N/S/E/W) from the point of view of
	 * current player (current player must be on south)
	 */
	function getPlayersToDirection() {
		$result = [];
		$players = self::loadPlayersBasicInfos();
		$nextPlayer = self::createNextPlayerTable(array_keys($players));
		$current_player = self::getCurrentPlayerId();

		// counterclockwise order
		$directions = ['S', 'W', 'N', 'E'];

		if (!isset($nextPlayer[$current_player])) {
			// Spectator mode: take any player for south
			$player_id = $nextPlayer[0];
			$result[$player_id] = array_shift($directions);
		} else {
			// Normal mode: current player is on south
			$player_id = $current_player;
			$result[$player_id] = array_shift($directions);
		}

		while (count($directions) > 0) {
			$player_id = $nextPlayer[$player_id];
			$result[$player_id] = array_shift($directions);
		}

		return $result;
	}

	function getPartnerIdOfPlayerId($playerId) {
		$players = self::loadPlayersBasicInfos();
		$nextPlayer = self::createNextPlayerTable(array_keys($players));
		$partnerId = $nextPlayer[$nextPlayer[$playerId]];
		return $partnerId;
	}

	/**
	 * Returns array[color][arg] => strength according to current trick
	 */
	private function getCardsStrengths() {
		$trickColor = self::getGameStateValue('trickColor');
		$trumpColor = self::getGameStateValue('trumpColor');

		$strengths = [];
		foreach ($this->colors as $colorId => $color) {
			if ($colorId == $trumpColor) {
				// Trump, stronger and specific order
				$strengths[$colorId] = [
					7 => 11,
					8 => 12,
					9 => 17,
					10 => 15,
					11 => 18,
					12 => 13,
					13 => 14,
					14 => 16,
				];
			} elseif ($colorId == $trickColor) {
				// Current trick color, stronger
				$strengths[$colorId] = [
					7 => 1,
					8 => 2,
					9 => 3,
					10 => 7,
					11 => 4,
					12 => 5,
					13 => 6,
					14 => 8,
				];
			} else {
				// No strength otherwise
				$strengths[$colorId] = [
					7 => 0,
					8 => 0,
					9 => 0,
					10 => 0,
					11 => 0,
					12 => 0,
					13 => 0,
					14 => 0,
				];
			}
		}
		return $strengths;
	}

	private function getCardStrength($card) {
		$cardsStrengths = $this->getCardsStrengths();
		$cardStrength = $cardsStrengths[$card['type']][$card['type_arg']];
		return $cardStrength;
	}

	function setNextFirstPlayer() {
		$firstPlayerId = self::getGameStateValue('firstPlayer');
		$players = self::loadPlayersBasicInfos();
		$firstPlayerIndex = array_search($firstPlayerId, array_keys($players));
		$firstPlayerIndex = ($firstPlayerIndex + 1) % 4;
		$firstPlayerId = array_keys($players)[$firstPlayerIndex];
		$firstPlayer = $players[$firstPlayerId];
		self::setGameStateValue('firstPlayer', $firstPlayerId);

		self::notifyAllPlayers(
			'firstPlayerChange',
			clienttranslate('${player_name} is now the first player'),
			[
				'i18n' => ['player_name'],
				'player_id' => $firstPlayerId,
				'player_name' => $firstPlayer['player_name'],
			]
		);
	}

	/**
	 * Assert that the card can be played.
	 *
	 * Throws an exception if not.
	 */
	function assertCardPlay($cardId) {
		$currentCard = $this->cards->getCard($cardId);
		$currentColor = $currentCard['type'];
		$trickColor = self::getGameStateValue('trickColor');
		$trumpColor = self::getGameStateValue('trumpColor');
		$playerId = self::getActivePlayerId();
		$playerCards = $this->cards->getCardsInLocation('hand', $playerId);
		$cardsOnTable = $this->cards->getCardsInLocation('cardsontable');
		$partnerId = $this->getPartnerIdOfPlayerId($playerId);

		$isCardInHand = false;
		$hasTrickColorInHand = false;
		$hasTrumpColorInHand = false;
		$trumpStrongestInHand = 0;
		$strongestTrickCard = null;
		$strongestTrickValue = 0;
		$hasTrumpBeenPlayed = false;
		$trumpStrongestPlayed = 0;
		$cardStrength = $this->getCardStrength($currentCard);

		foreach ($playerCards as $playerCard) {
			if ($playerCard['id'] === $cardId) {
				$isCardInHand = true;
				$currentCard = $playerCard;
			}

			if ($playerCard['type'] === $trickColor) {
				$hasTrickColorInHand = true;
			}

			if ($playerCard['type'] === $trumpColor) {
				$hasTrumpColorInHand = true;
				$strength = $this->getCardStrength($playerCard);
				if ($strength > $trumpStrongestInHand) {
					$trumpStrongestInHand = $strength;
				}
			}
		}

		// Loop cards on table
		foreach ($cardsOnTable as $cardOnTable) {
			$strength = $this->getCardStrength($cardOnTable);

			// Keep track if trump is played, register strongest value
			if ($cardOnTable['type'] === $trumpColor) {
				$hasTrumpBeenPlayed = true;
				if ($strength > $trumpStrongestPlayed) {
					$trumpStrongestPlayed = $strength;
				}
			}
			// Keep track of strongest card for current trickColor
			if ($cardOnTable['type'] === $trickColor) {
				if ($strength > $strongestTrickValue) {
					$strongestTrickCard = $cardOnTable;
					$strongestTrickValue = $strength;
				}
			}
		}

		if (!$isCardInHand) {
			// Woaw !
			throw new BgaUserException('Card is not in hand');
		}

		if ($trickColor == 0) {
			// First card, no check
			return;
		}

		if ($trickColor != $currentColor) {
			// currentColor is not trick color

			if ($hasTrickColorInHand) {
				// Player has trick color in hand, must play one
				throw new BgaUserException(
					sprintf(
						self::_('You must play a %s'),
						$this->colors[$trickColor]['nametr']
					)
				);
			}

			self::debug(
				'<pre>' .
					var_export(
						[
							'currentCard' => $currentCard,
							'cardStrength' => $cardStrength,
							'trickColor' => $trickColor,
							'trumpColor' => $trumpColor,
							'partnerId' => $partnerId,
							'isCardInHand' => $isCardInHand,
							'hasTrickColorInHand' => $hasTrickColorInHand,
							'hasTrumpColorInHand' => $hasTrumpColorInHand,
							'trumpStrongestInHand' => $trumpStrongestInHand,
							'hasTrumpBeenPlayed' => $hasTrumpBeenPlayed,
							'trumpStrongestPlayed' => $trumpStrongestPlayed,
							'strongestTrickCard' => $strongestTrickCard,
							'strongestTrickValue' => $strongestTrickValue,
						],
						true
					) .
					'</pre>'
			);
			if ($hasTrumpColorInHand && $currentColor !== $trumpColor) {
				// Player has trump color in hand;
				// It must play one if its partner is not the strongest
				if (
					$hasTrumpBeenPlayed ||
					$strongestTrickCard['location_arg'] !== "$partnerId"
				) {
					throw new BgaUserException(
						sprintf(
							self::_('You must cut with a %s'),
							$this->colors[$trumpColor]['nametr']
						)
					);
				}
			}
		}

		if ($currentColor === $trumpColor) {
			// Trump is played: check if going up
			if (
				$trumpStrongestInHand > $trumpStrongestPlayed &&
				$cardStrength <= $trumpStrongestPlayed
			) {
				throw new BgaUserException(
					sprintf(
						self::_('You must play a %s higher'),
						$this->colors[$trumpColor]['nametr']
					)
				);
			}
		}
	}

	function notifyBid($showMessage = false) {
		$message = '';
		if ($showMessage) {
			$message = clienttranslate(
				'${player_name} bids ${bid} ${trumpColorDisplay}'
			);
		}

		$players = self::loadPlayersBasicInfos();
		$trumpColor = self::getGameStateValue('trumpColor');
		$trumpColorDisplay = '';
		$bid = self::getGameStateValue('bid');
		$bidPlayerId = self::getGameStateValue('bidPlayer');
		$bidPlayerDisplay = '';
		$countered = self::getGameStateValue('countered');
		$counteringPlayerId = self::getGameStateValue('counteringPlayer');
		$counteringPlayerDisplay = '';

		if ($bidPlayerId) {
			$bidPlayer = $players[$bidPlayerId];
			$bidPlayerDisplay = $bidPlayer['player_name'];
		}
		if ($trumpColor) {
			$trumpColorDisplay = $this->colors[$trumpColor]['name'];
		}
		if ($countered) {
			$counteringPlayer = $players[$counteringPlayerId];
			$counteringPlayerDisplay = $counteringPlayer['player_name'];
		}

		self::notifyAllPlayers('updateBid', $message, [
			'i18n' => ['trumpColorDisplay', 'bidPlayerDisplay'],
			'bid' => $bid,
			'player_id' => $bidPlayerId,
			'player_name' => $bidPlayerDisplay,
			'trumpColor' => $trumpColor,
			'trumpColorDisplay' => $trumpColorDisplay,
			'bidPlayer' => $bidPlayerId,
			'bidPlayerDisplay' => $bidPlayerDisplay,
			'countered' => $countered,
			'counteringPlayer' => $counteringPlayerId,
			'counteringPlayerDisplay' => $counteringPlayerDisplay,
		]);
	}

	/*
        In this space, you can put any utility methods useful for your game logic
    */
	//////////////////////////////////////////////////////////////////////////////
	//////////// Player actions
	////////////

	function bid($color, $value) {
		self::checkAction('bid');
		$playerId = self::getActivePlayerId();

		// Check rules
		$previousValue = self::getGameStateValue('bid');
		$previousColor = self::getGameStateValue('trumpColor');
		$previousBidPlayer = self::getGameStateValue('bidPlayer');

		// Bid must go up
		if ($previousValue && $value <= $previousValue) {
			throw new BgaUserException(
				self::_("You must bid higher than current bid ($previousValue)")
			);
		}
		// Bid must change color if same player
		if ($previousColor == $color && $playerId == $previousBidPlayer) {
			throw new BgaUserException(
				self::_('You must change color to bid higher on yourself')
			);
		}

		self::setGameStateValue('bid', $value);
		self::setGameStateValue('trumpColor', $color);
		self::setGameStateValue('bidPlayer', $playerId);
		self::setGameStateValue('passCount', 0);

		$this->notifyBid(true);

		// Next player
		$this->gamestate->nextState('nextPlayerBid');
	}

	function pass() {
		self::checkAction('pass');
		$playerId = self::getActivePlayerId();

		// And notify
		self::notifyAllPlayers(
			'updateBidPass',
			clienttranslate('${player_name} passes'),
			[
				'player_id' => $playerId,
				'player_name' => self::getActivePlayerName(),
			]
		);

		// Next player
		$passCount = self::getGameStateValue('passCount');
		self::setGameStateValue('passCount', $passCount + 1);
		$this->gamestate->nextState('nextPlayerBid');
	}

	function coinche() {
		$this->gamestate->checkPossibleAction('coinche');


		// Check coinche is possible
		$playerId = self::getCurrentPlayerId();
		$bid = self::getGameStateValue('bid');
		$bidPlayerId = self::getGameStateValue('bidPlayer');
		$partnerId = $this->getPartnerIdOfPlayerId($playerId);
		if (!$bid) {
			throw new BgaUserException(self::_('Cannot counter on no bid'));
		}
		if ($playerId == $bidPlayerId) {
			throw new BgaUserException(self::_('Cannot counter on you own bid'));
		}
		if ($partnerId == $bidPlayerId) {
			throw new BgaUserException(self::_('Cannot counter on you partner\'s bid'));
		}

		// Next player
		self::setGameStateValue('countered', 1);
		self::setGameStateValue('counteringPlayer', $playerId);
		//
		// And notify
		self::notifyAllPlayers(
			'updateBidCoinche',
			clienttranslate('${player_name} coinches'),
			[
				'player_id' => $playerId,
				'player_name' => self::getCurrentPlayerName(),
			]
		);

		$this->notifyBid();
		$this->gamestate->nextState('nextPlayerBid');
	}

	function playCard($cardId) {
		self::checkAction('playCard');
		$playerId = self::getActivePlayerId();

		// Check Rules
		$this->assertCardPlay($cardId);

		$this->cards->moveCard($cardId, 'cardsontable', $playerId);
		$currentCard = $this->cards->getCard($cardId);

		// Update current trick color if starting trick
		$trickColor = self::getGameStateValue('trickColor');
		if ($trickColor == 0) {
			self::setGameStateValue('trickColor', $currentCard['type']);
		}

		// And notify
		self::notifyAllPlayers(
			'playCard',
			clienttranslate(
				'${player_name} plays ${value_displayed} ${color_displayed}'
			),
			[
				'i18n' => ['color_displayed', 'value_displayed'],
				'card_id' => $cardId,
				'player_id' => $playerId,
				'player_name' => self::getActivePlayerName(),
				'value' => $currentCard['type_arg'],
				'value_displayed' => $this->values_label[$currentCard['type_arg']],
				'color' => $currentCard['type'],
				'color_displayed' => $this->colors[$currentCard['type']]['name'],
			]
		);
		// Next player
		$this->gamestate->nextState('playCard');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state arguments
	////////////

	function argGiveCards() {
		return [];
	}
	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state actions
	////////////

	function stNewHand() {
		// Take back all cards (from any location => null) to deck
		$this->cards->moveAllCardsInLocation(null, 'deck');
		$this->cards->shuffle('deck');
		// Deal 8 cards to each players
		// Create deck, shuffle it and give 8 initial cards
		$players = self::loadPlayersBasicInfos();
		foreach ($players as $playerId => $player) {
			$cards = $this->cards->pickCards(8, 'deck', $playerId);
			// Notify player about his cards
			self::notifyPlayer($playerId, 'newHand', '', [
				'cards' => $cards,
			]);
		}
		self::setGameStateInitialValue('trumpColor', 0);
		self::setGameStateInitialValue('bid', 0);
		self::setGameStateInitialValue('bidPlayer', 0);
		self::setGameStateInitialValue('countered', 0);

		$this->notifyBid();

		$this->gamestate->nextState('');
	}

	function stStartBidding() {
		self::setGameStateInitialValue('trumpColor', 0);
		self::setGameStateInitialValue('bid', 0);
		self::setGameStateInitialValue('bidPlayer', 0);
		self::setGameStateInitialValue('countered', 0);
		self::setGameStateInitialValue('passCount', 0);
		$this->gamestate->nextState('');
	}

	function stNextPlayerBid() {
		$countered = self::getGameStateValue('countered');
		if ($countered > 0) {
			// Bid ok, activate 'first' player and start playing
			$firstPlayerId = self::getGameStateValue('firstPlayer');
			$this->gamestate->changeActivePlayer($firstPlayerId);
			$this->gamestate->nextState('endBidding');
			// TODO notify bidding & coinche
			return;
		}

		$passCount = self::getGameStateValue('passCount');
		if ($passCount >= 4) {
			// Last pass
			$bid = self::getGameStateValue('bid');

			// No bid -> new hand
			if ($bid == 0) {
				self::notifyAllPlayers(
					'allPassNoBid',
					clienttranslate('Everybody passes, no bid'),
					[]
				);
				$this->setNextFirstPlayer();
				$firstPlayerId = self::getGameStateValue('firstPlayer');
				$this->gamestate->changeActivePlayer($firstPlayerId);
				$this->gamestate->nextState('newHand');
				return;
			}

			// Bid ok, activate 'first' player and start playing
			$firstPlayerId = self::getGameStateValue('firstPlayer');
			$this->gamestate->changeActivePlayer($firstPlayerId);
			$this->gamestate->nextState('endBidding');
			self::notifyAllPlayers(
				'allPassWithBid',
				clienttranslate('Everybody passes, bid accepted'),
				[]
			);
			return;
		}

		$playerId = self::activeNextPlayer();
		self::giveExtraTime($playerId);
		$this->gamestate->nextState('nextPlayerBid');
	}

	function stNewTrick() {
		// New trick: active the player who wins the last trick
		// Reset trick color to 0 (= no color)

		self::setGameStateInitialValue('trickColor', 0);
		$this->gamestate->nextState();
	}

	function stNextPlayer() {
		// Active next player OR end the trick and go to the next trick OR end the hand
		if ($this->cards->countCardInLocation('cardsontable') == 4) {
			// This is the end of the trick
			$cardsOnTable = $this->cards->getCardsInLocation('cardsontable');

			$strongerValue = 0;

			$winningCard = null;
			foreach ($cardsOnTable as $card) {
				$strength = $this->getCardStrength($card);

				// First card
				if ($winningCard === null) {
					$winningCard = $card;
					$strongerValue = $strength;
					continue;
				}

				if ($strength > $strongerValue) {
					$winningCard = $card;
					$strongerValue = $strength;
				}
			}

			$bestValuePlayerId = $winningCard['location_arg']; // Note: location_arg = player who played this card on table
			// Move all cards to "cardswon" of the given player
			$this->cards->moveAllCardsInLocation(
				'cardsontable',
				'cardswon',
				null,
				$bestValuePlayerId
			);

			// Notify
			// Note: we use 2 notifications here in order we can pause the display during the first notification
			//  before we move all cards to the winner (during the second)
			$players = self::loadPlayersBasicInfos();
			self::notifyAllPlayers(
				'trickWin',
				clienttranslate('${player_name} wins the trick'),
				[
					'player_id' => $bestValuePlayerId,
					'player_name' => $players[$bestValuePlayerId]['player_name'],
				]
			);
			self::notifyAllPlayers('giveAllCardsToPlayer', '', [
				'player_id' => $bestValuePlayerId,
			]);

			if ($this->cards->countCardInLocation('hand') == 0) {
				// End of the hand
				$this->gamestate->nextState('endHand');
			} else {
				// End of the trick
				$this->gamestate->changeActivePlayer($bestValuePlayerId);
				$this->gamestate->nextState('nextTrick');
			}
		} else {
			// Standard case (not the end of the trick)
			// => just active the next player
			$playerId = self::activeNextPlayer();
			self::giveExtraTime($playerId);
			$this->gamestate->nextState('nextPlayer');
		}
	}

	function stEndHand() {
		// TODO Set next "first player"
		$this->gamestate->nextState('nextHand');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Zombie
	////////////

	/*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

	function zombieTurn($state, $active_player) {
		$statename = $state['name'];

		if ($state['type'] === 'activeplayer') {
			switch ($statename) {
				default:
					$this->gamestate->nextState('zombiePass');
					break;
			}

			return;
		}

		if ($state['type'] === 'multipleactiveplayer') {
			// Make sure player is in a non blocking status for role turn
			$this->gamestate->setPlayerNonMultiactive($active_player, '');

			return;
		}

		throw new feException(
			'Zombie mode not supported at this game state: ' . $statename
		);
	}

	///////////////////////////////////////////////////////////////////////////////////:
	////////// DB upgrade
	//////////

	/*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

	function upgradeTableDb($from_version) {
		// $from_version is the current version of this game database, in numerical form.
		// For example, if the game was running with a release of your game named "140430-1345",
		// $from_version is equal to 1404301345
		// Example:
		//        if( $from_version <= 1404301345 )
		//        {
		//            // ! important ! Use DBPREFIX_<table_name> for all tables
		//
		//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
		//            self::applyDbUpgradeToAllDB( $sql );
		//        }
		//        if( $from_version <= 1405061421 )
		//        {
		//            // ! important ! Use DBPREFIX_<table_name> for all tables
		//
		//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
		//            self::applyDbUpgradeToAllDB( $sql );
		//        }
		//        // Please add your future database scheme changes here
		//
		//
	}
}
