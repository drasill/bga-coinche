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
		// Your global variables labels:
		//  Here, you can assign labels to global variables you are using for this game.
		//  You can use any number of global variables with IDs between 10 and 99.
		//  If your game has options (variants), you also have to associate here a label to
		//  the corresponding ID in gameoptions.inc.php.
		// Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
		parent::__construct();

		self::initGameStateLabels(array(
			'currentHandType' => 10,
			'trickColor' => 11,
			'trumpColor' => 12,
			'bid' => 13,
			'countered' => 14,
		));

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
	protected function setupNewGame($players, $options = array()) {
		// Set the colors of the players with HTML color code
		// The default below is red/green/blue/orange/brown
		// The number of colors defined here must correspond to the maximum number of players allowed for the gams
		$gameinfos = self::getGameinfos();
		$default_colors = $gameinfos['player_colors'];

		// Create players
		// Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
		$sql =
			'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
		$values = array();
		foreach ($players as $player_id => $player) {
			$color = array_shift($default_colors);
			$values[] =
				"('" .
				$player_id .
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
		self::setGameStateInitialValue('bid', 82);

		// 0: non coinché, 1: coinché, 2: surcoinché
		self::setGameStateInitialValue('countered', 0);

		// Create cards
		$cards = array();
		foreach ($this->colors as $color_id => $color) {
			// spade, heart, diamond, club
			for ($value = 7; $value <= 14; $value++) {
				//  7, 8, 9, 10, J, Q, K, A
				$cards[] = array(
					'type' => $color_id,
					'type_arg' => $value,
					'nbr' => 1,
				);
			}
		}
		$this->cards->createCards($cards, 'deck');

		// Shuffle deck
		$this->cards->shuffle('deck');
		// Deal 8 cards to each players
		$players = self::loadPlayersBasicInfos();
		foreach ($players as $player_id => $player) {
			$cards = $this->cards->pickCards(8, 'deck', $player_id);
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
		$result = array();

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

		return $result;
	}

	/*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
	function getGameProgression() {
		// TODO: compute and return the game progression

		return 0;
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Utility functions
	////////////

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
					7 => 1,
					8 => 2,
					9 => 7,
					10 => 5,
					11 => 8,
					12 => 3,
					13 => 4,
					14 => 6,
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

	/*
        In this space, you can put any utility methods useful for your game logic
    */
	//////////////////////////////////////////////////////////////////////////////
	//////////// Player actions
	////////////

	function playCard($card_id) {
		self::checkAction('playCard');
		$player_id = self::getActivePlayerId();
		$this->cards->moveCard($card_id, 'cardsontable', $player_id);
		// XXX check rules here
		$currentCard = $this->cards->getCard($card_id);

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
			array(
				'i18n' => array('color_displayed', 'value_displayed'),
				'card_id' => $card_id,
				'player_id' => $player_id,
				'player_name' => self::getActivePlayerName(),
				'value' => $currentCard['type_arg'],
				'value_displayed' => $this->values_label[$currentCard['type_arg']],
				'color' => $currentCard['type'],
				'color_displayed' => $this->colors[$currentCard['type']]['name'],
			)
		);
		// Next player
		$this->gamestate->nextState('playCard');
	}

	/*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */

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
		foreach ($players as $player_id => $player) {
			$cards = $this->cards->pickCards(8, 'deck', $player_id);
			// Notify player about his cards
			self::notifyPlayer($player_id, 'newHand', '', array(
				'cards' => $cards,
			));
		}
		self::setGameStateInitialValue('trumpColor', 0);
		self::setGameStateInitialValue('bid', 82);
		self::setGameStateInitialValue('countered', 0);

		$this->gamestate->nextState('');
	}

	function stNewTrick() {
		// New trick: active the player who wins the last trick, or the player who own the club-2 card
		// Reset trick color to 0 (= no color)
		self::setGameStateInitialValue('trickColor', 0);
		$this->gamestate->nextState();
	}

	function stNextPlayer() {
		// Active next player OR end the trick and go to the next trick OR end the hand
		if ($this->cards->countCardInLocation('cardsontable') == 4) {
			// This is the end of the trick
			$cardsOnTable = $this->cards->getCardsInLocation('cardsontable');

			$cardStrengths = $this->getCardsStrengths();
			$strongerValue = 0;

			$winningCard = null;
			foreach ($cardsOnTable as $card) {
				$strength = $cardStrengths[$card['type']][$card['type_arg']];

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
				array(
					'player_id' => $bestValuePlayerId,
					'player_name' => $players[$bestValuePlayerId]['player_name'],
				)
			);
			self::notifyAllPlayers('giveAllCardsToPlayer', '', array(
				'player_id' => $bestValuePlayerId,
			));

			if ($this->cards->countCardInLocation('hand') == 0) {
				// End of the hand
				$this->gamestate->nextState('endHand');
			} else {
				// End of the trick
				$this->gamestate->nextState('nextTrick');
			}
		} else {
			// Standard case (not the end of the trick)
			// => just active the next player
			$player_id = self::activeNextPlayer();
			self::giveExtraTime($player_id);
			$this->gamestate->nextState('nextPlayer');
		}
	}

	function stEndHand() {
		$this->gamestate->nextState('nextHand');
	}

	/*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */

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