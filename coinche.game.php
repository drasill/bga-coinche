<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Coinche implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * coinche.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';

class Coinche extends Table {
	// Team pairing constants
	const TEAM_1_3 = 1;
	const TEAM_1_2 = 2;
	const TEAM_1_4 = 3;
	const TEAM_RANDOM = 4;

	function __construct() {
		parent::__construct();

		self::initGameStateLabels([
			// First Player for current hand
			'firstPlayer' => 11,
			// Current trick color (zero = no trick color)
			'trickColor' => 12,
			// Current trump color (zero = no trump color, 5 = alltrump, 6 = notrump)
			'trumpColor' => 13,
			// Current bid value (from 82 to 170)
			'bid' => 14,
			// Player who is currently winning the bidding
			'bidPlayer' => 15,
			// Countered ("coinché") : 0 = not doubled, 1 = doubled, 2 = redoubled
			'countered' => 16,
			// Player who did double
			'counteringPlayer' => 17,
			// Player who did redouble
			'recounteringPlayer' => 25,
			// Number of successive player passes (to trigger end of bidding)
			'passCount' => 18,
			// Current trick count (of current hand)
			'trickCount' => 19,

			// Belote (Queen & King) informations
			'beloteCardId1' => 20,
			'beloteCardId2' => 21,
			'belotePlayerId' => 22,
			'beloteDeclared' => 23,
			'dixDeDerPlayerId' => 24,

			// Options
			// Game length -> max score
			'gameLength' => 100,
			// Score type
			'scoreType' => 101,
			// Player teams (taken from the game "belote")
			'playerTeams' => 102,
			// AllTrum/NoTrump available ? (1 yes, 2 no)
			'hasAllNoTrumps' => 103,
		]);

		$this->cards = self::getNew('module.common.deck');
		$this->cards->init('card');
	}

	protected function getGameName() {
		// Used for translations and stuff. Please do not modify.
		return 'coinche';
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

		$playerInitialOrder = array_column($players, 'player_table_order');

		// Player order based on 'playerTeams' option
		$playerOrder = [0, 1, 2, 3];
		switch (self::getGameStateValue('playerTeams')) {
			case self::TEAM_1_2:
				$playerOrder = [0, 2, 1, 3];
				break;
			case self::TEAM_1_4:
				$playerOrder = [0, 1, 3, 2];
				break;
			case self::TEAM_RANDOM:
				shuffle($playerOrder);
				break;
			default:
			case self::TEAM_1_3:
				// Default order
				break;
		}

		// Create players
		// Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
		$sql =
			'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar, player_no) VALUES ';
		$values = [];

		$playerIndex = 0;
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
				"','" .
				$playerOrder[$playerInitialOrder[$playerIndex] - 1] .
				"')";
			$playerIndex++;
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

		self::setGameStateInitialValue('trickColor', 0);
		self::setGameStateInitialValue('trumpColor', 0);
		self::setGameStateInitialValue('bid', 0);
		self::setGameStateInitialValue('countered', 0);
		self::setGameStateInitialValue('counteringPlayer', 0);
		self::setGameStateInitialValue('recounteringPlayer', 0);
		self::setGameStateInitialValue('passCount', 0);
		self::setGameStateInitialValue('trickCount', 0);
		self::setGameStateInitialValue('beloteCardId1', 0);
		self::setGameStateInitialValue('beloteCardId2', 0);
		self::setGameStateInitialValue('belotePlayerId', 0);
		self::setGameStateInitialValue('beloteDeclared', 0);
		self::setGameStateInitialValue('dixDeDerPlayerId', 0);

		$firstPlayerId = array_rand($players, 1);
		self::setGameStateInitialValue('firstPlayer', $firstPlayerId);

		// Init game statistics
		self::initStat('table', 'numberOfHands', 0);
		self::initStat('table', 'numberOfHandsPlayed', 0);
		self::initStat('table', 'numberOfCounters', 0);
		self::initStat('player', 'numberOfHandsTaken', 0);
		self::initStat('player', 'numberOfBidSucceeded', 0);
		self::initStat('player', 'numberOfBidsFailed', 0);
		self::initStat('player', 'numberOfCounters', 0);
		self::initStat('player', 'numberOfCountersSuccess', 0);

		// Create cards
		$cards = [];
		foreach ($this->colors as $color_id => $color) {
			if ($color_id > 4) {
				continue;
			}
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

		$this->activateFirstPlayer();

		/************ End of the game initialization *****/
	}

	/*
	 * getAllDatas:
	 *
	 * Gather all informations about current game situation (visible by the current player).
	 *
	 * The method is called each time the game interface is displayed to a player, ie:
	 * _ when the game starts
	 * _ when a player refreshes the game page (F5)
	 */
	protected function getAllDatas() {
		$result = [];

		$currentPlayerId = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!

		// Get information about players
		// Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
		$sql =
			'SELECT player_id id, player_score score, player_no, player_tricks tricks FROM player ';
		$result['players'] = self::getCollectionFromDb($sql);

		// Cards in player hand
		$result['hand'] = $this->cards->getCardsInLocation(
			'hand',
			$currentPlayerId
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
		$recounteringPlayer = self::getGameStateValue('recounteringPlayer');

		$result['hasAllNoTrumps'] =
			self::getGameStateValue('hasAllNoTrumps') == 1;
		$result['trumpColor'] = $trumpColor;
		$result['trumpColorDisplay'] = $this->colors[$trumpColor]['name'] ?? null;
		$result['bid'] = $bid;
		$result['bidPlayer'] = $bidPlayer;
		$result['bidPlayerDisplay'] = $players[$bidPlayer]['player_name'] ?? null;
		$result['countered'] = $countered;
		$result['counteringPlayer'] = $counteringPlayer;
		$result['recounteringPlayer'] = $recounteringPlayer;
		$result['counteringPlayerDisplay'] =
			$players[$counteringPlayer]['player_name'] ?? '';
		$result['recounteringPlayerDisplay'] =
			$players[$recounteringPlayer]['player_name'] ?? '';
		$result['firstPlayer'] = $firstPlayer;

		// Inform current player of the belote cards & status (if they have it)
		$belotePlayerId = self::getGameStateValue('belotePlayerId');
		if ($belotePlayerId == $currentPlayerId) {
			$result['belote_card_id_1'] = self::getGameStateValue('beloteCardId1');
			$result['belote_card_id_2'] = self::getGameStateValue('beloteCardId2');
			$result['belote_declared'] = self::getGameStateValue('beloteDeclared');
		} else {
			$result['belote_card_id_1'] = null;
			$result['belote_card_id_2'] = null;
			$result['belote_declared'] = null;
		}

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
	public function getGameProgression() {
		// Shamelessly taken from the game "belote"
		$maxScore = $this->getMaxScore();
		$playerMaxScore = self::getUniqueValueFromDb(
			'SELECT MAX( player_score ) FROM player'
		);
		$playerMinScore = self::getUniqueValueFromDb(
			'SELECT MIN( player_score ) FROM player'
		);

		if ($playerMaxScore > $maxScore) {
			// End
			return 100;
		}
		if ($playerMaxScore <= 0) {
			// Start
			return 0;
		}

		// Average
		$n = 2 * ($maxScore - $playerMaxScore);
		$res =
			(100 * ($playerMaxScore + $playerMinScore)) /
			($n + $playerMaxScore + $playerMinScore);
		return max(0, min(100, $res)); // Note: 0 => 100
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Utility functions
	////////////

	/**
	 * Compute which cards are the belote, and which player has them.
	 * Then notify the player.
	 */
	private function findAndNotifyBelote() {
		self::setGameStateValue('beloteCardId1', 0);
		self::setGameStateValue('beloteCardId2', 0);
		self::setGameStateValue('belotePlayerId', 0);
		self::setGameStateValue('beloteDeclared', 0);

		$trumpColor = self::getGameStateValue('trumpColor');

		if ($trumpColor > 4) {
			// Alltrump/notrump, no belote
			return;
		}
		$players = self::loadPlayersBasicInfos();
		$cardIds = [];
		foreach ($players as $player) {
			$playerCards = $this->cards->getCardsInLocation(
				'hand',
				$player['player_id']
			);
			$cardIds = [];
			foreach ($playerCards as $card) {
				if (
					$card['type'] == $trumpColor &&
					in_array($card['type_arg'], [12, 13])
				) {
					$cardIds[] = $card['id'];
				}
			}
			if (count($cardIds) === 2) {
				// Found you !
				break;
			}
		}

		// No belote in any hand
		if (count($cardIds) !== 2) {
			return;
		}

		self::setGameStateValue('beloteCardId1', $cardIds[0]);
		self::setGameStateValue('beloteCardId2', $cardIds[1]);
		self::setGameStateValue('belotePlayerId', $player['player_id']);

		$this->notifyBelote();
	}

	/**
	 * Notify the player who has belote
	 */
	private function notifyBelote() {
		$playerId = self::getGameStateValue('belotePlayerId');
		if (!$playerId) {
			return;
		}
		$cardId1 = self::getGameStateValue('beloteCardId1');
		$cardId2 = self::getGameStateValue('beloteCardId2');
		$declared = !!self::getGameStateValue('beloteDeclared');
		self::notifyPlayer($playerId, 'belote', '', [
			'belote_card_id_1' => $cardId1,
			'belote_card_id_2' => $cardId2,
			'belote_declared' => $declared,
		]);
	}

	/**
	 * Returns the maximum score to end the game
	 */
	private function getMaxScore() {
		$gameLength = self::getGameStateValue('gameLength');
		if ($gameLength == 1) {
			return 2000;
		}
		if ($gameLength == 2) {
			return 1000;
		}
		throw new BgaVisibleSystemException(
			'Error, gameLength value is not in [1,2]' // NOI18N
		);
	}

	/**
	 * Return players => direction (N/S/E/W) from the point of view of
	 * current player (current player must be on south)
	 */
	public function getPlayersToDirection() {
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

	private function getPartnerIdOfPlayerId($playerId) {
		$players = self::loadPlayersBasicInfos();
		$nextPlayer = self::createNextPlayerTable(array_keys($players));
		$partnerId = $nextPlayer[$nextPlayer[$playerId]];
		return $partnerId;
	}

	/**
	 * Returns array[color][arg] => strength according to current trump color
	 */
	private function getCardsPoints() {
		$trumpColor = self::getGameStateValue('trumpColor');
		$points = [];
		foreach ($this->colors as $colorId => $color) {
			if ($colorId == $trumpColor || $trumpColor == 5) {
				// Trump
				$points[$colorId] = [
					7 => 0,
					8 => 0,
					9 => 14,
					10 => 10,
					11 => 20,
					12 => 3,
					13 => 4,
					14 => 11,
				];
			} else {
				// Normal
				$points[$colorId] = [
					7 => 0,
					8 => 0,
					9 => 0,
					10 => 10,
					11 => 2,
					12 => 3,
					13 => 4,
					14 => 11,
				];
			}
		}
		return $points;
	}

	/**
	 * Returns array[color][arg] => strength according to current trick
	 */
	private function getCardsStrengths() {
		$trickColor = self::getGameStateValue('trickColor');
		$trumpColor = self::getGameStateValue('trumpColor');

		$strengths = [];
		foreach ($this->colors as $colorId => $color) {
			if ($trumpColor == 5) {
				// All trump
				if ($colorId == $trickColor) {
					// Current trick color, stronger and specific order
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
			} else {
				// Normal or no trump
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
		}
		return $strengths;
	}

	/**
	 * Returns the strength of a card array
	 */
	private function getCardStrength($card) {
		$cardsStrengths = $this->getCardsStrengths();
		$cardStrength = $cardsStrengths[$card['type']][$card['type_arg']];
		return $cardStrength;
	}

	/**
	 * Set the firstPlayer as next
	 */
	private function setNextFirstPlayer() {
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
	 * Set the first player as "active"
	 */
	private function activateFirstPlayer() {
		$firstPlayerId = self::getGameStateValue('firstPlayer');
		$this->gamestate->changeActivePlayer($firstPlayerId);
	}

	/**
	 * Assert that the card can be played.
	 *
	 * Throws an exception if not.
	 */
	private function assertCardPlay($cardId) {
		$card = $this->cards->getCard($cardId);
		$cardColor = $card['type'];
		$cardStrength = $this->getCardStrength($card);

		$trickColor = self::getGameStateValue('trickColor');
		$trumpColor = self::getGameStateValue('trumpColor');
		$playerId = self::getActivePlayerId();
		$playerCards = $this->cards->getCardsInLocation('hand', $playerId);
		$cardsOnTable = $this->cards->getCardsInLocation('cardsontable');
		$partnerId = $this->getPartnerIdOfPlayerId($playerId);

		$isCardInHand = false;
		$hasTrickColorInHand = false;
		$hasTrumpColorInHand = false;

		// Strongest trump card in player's hand
		$trumpStrongestInHand = 0;
		// Strongest trickcolor card in player's hand
		$trickStrongestInHand = 0;

		// Strongest trickcolor card played on table
		$strongestTrickCardOnTable = null;
		$strongestTrickCardStrengthOnTable = 0;
		$hasTrumpBeenPlayed = false;
		// Strongest trump card played on table
		$strongestTrumpCardOnTable = null;
		$strongestTrumpCardStrengthOnTable = 0;

		// Is the partner the currently strongest of this trick ?
		$isPartnerTheStrongest = false;

		// First card, no check
		if ($trickColor == 0) {
			return;
		}

		// Loop on player's cards to set the 'inhand' values
		foreach ($playerCards as $playerCard) {
			if ($playerCard['id'] === $cardId) {
				$isCardInHand = true;
			}

			$strength = $this->getCardStrength($playerCard);

			if ($playerCard['type'] === $trickColor) {
				$hasTrickColorInHand = true;
				if ($strength > $trickStrongestInHand) {
					$trickStrongestInHand = $strength;
				}
			}

			if ($playerCard['type'] === $trumpColor) {
				$hasTrumpColorInHand = true;
				if ($strength > $trumpStrongestInHand) {
					$trumpStrongestInHand = $strength;
				}
			}
		}

		// Loop cards on table to get the 'ontable' values
		foreach ($cardsOnTable as $cardOnTable) {
			$strength = $this->getCardStrength($cardOnTable);

			// Keep track if trump is played, register strongest value
			if ($cardOnTable['type'] === $trumpColor) {
				$hasTrumpBeenPlayed = true;
				if ($strength > $strongestTrumpCardStrengthOnTable) {
					$strongestTrumpCardOnTable = $cardOnTable;
					$strongestTrumpCardStrengthOnTable = $strength;
				}
			}
			// Keep track of strongest card for current trickColor
			if ($cardOnTable['type'] === $trickColor) {
				if ($strength > $strongestTrickCardStrengthOnTable) {
					$strongestTrickCardOnTable = $cardOnTable;
					$strongestTrickCardStrengthOnTable = $strength;
				}
			}
		}

		// Look up if partner has played the strongest card
		if ($hasTrumpBeenPlayed) {
			// When trump has been played...
			$isPartnerTheStrongest =
				$strongestTrumpCardOnTable['location_arg'] === "$partnerId";
		} else {
			// Otherwise.
			$isPartnerTheStrongest =
				$strongestTrickCardOnTable['location_arg'] === "$partnerId";
		}

		if (!$isCardInHand) {
			// Woaw ! Should not happen.
			throw new BgaUserException('Card is not in hand'); // NOI18N
		}

		if ($trickColor != $cardColor) {
			// cardColor is not trick color

			if ($hasTrickColorInHand) {
				// Player has trick color in hand, must play one
				throw new BgaUserException(
					sprintf(
						self::_('You must play a %s'),
						$this->colors[$trickColor]['nametr']
					)
				);
			}

			if ($hasTrumpColorInHand && $cardColor !== $trumpColor) {
				// Player has trump color in hand;
				// It must play one if its partner is not the strongest
				if (!$isPartnerTheStrongest) {
					throw new BgaUserException(
						sprintf(
							self::_('You must cut with a %s'),
							$this->colors[$trumpColor]['nametr']
						)
					);
				}
			}
		}

		if ($trumpColor == 5 && $trickColor === $cardColor) {
			// All trump: check if going up, if same as trick color
			if (
				$trickStrongestInHand > $strongestTrickCardStrengthOnTable &&
				$cardStrength <= $strongestTrickCardStrengthOnTable
			) {
				throw new BgaUserException(
					sprintf(
						self::_('You must play a %s higher (all trump)'),
						$this->colors[$trickColor]['nametr']
					)
				);
			}
		}

		if ($cardColor === $trumpColor) {
			// Trump is played: check if going up
			if (
				$trumpStrongestInHand > $strongestTrumpCardStrengthOnTable &&
				$cardStrength <= $strongestTrumpCardStrengthOnTable
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

	private function getPossibleCardsToPlay($playerId) {
		// Loop the player hand, stopping at the first card which can be played
		$playerCards = $this->cards->getCardsInLocation('hand', $playerId);
		$possibleCards = [];
		foreach ($playerCards as $playerCard) {
			try {
				$this->assertCardPlay($playerCard['id']);
			} catch (\Exception $e) {
				continue;
			}
			$possibleCards[] = $playerCard;
		}
		return $possibleCards;
	}

	/**
	 * Notify the current bid informations
	 */
	private function notifyBid($showMessage = false) {
		$message = '';
		if ($showMessage) {
			$message = clienttranslate(
				'${player_name} bids ${bid_value} ${color_symbol} (${trumpColorDisplay})'
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
		$recounteringPlayerId = self::getGameStateValue('recounteringPlayer');
		$counteringPlayerDisplay = '';
		$recounteringPlayerDisplay = '';

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
			if ($countered == 2) {
				$recounteringPlayer = $players[$recounteringPlayerId];
				$recounteringPlayerDisplay = $recounteringPlayer['player_name'];
			}
		}

		self::notifyAllPlayers('updateBid', $message, [
			'i18n' => ['trumpColorDisplay', 'bidPlayerDisplay'],
			'bid' => $bid,
			'player_id' => $bidPlayerId,
			'player_name' => $bidPlayerDisplay,
			'color_symbol' => $trumpColor,
			'bid_value' => $bid,
			'trumpColor' => $trumpColor,
			'trumpColorDisplay' => $trumpColorDisplay,
			'bidPlayer' => $bidPlayerId,
			'bidPlayerDisplay' => $bidPlayerDisplay,
			'countered' => $countered,
			'counteringPlayer' => $counteringPlayerId,
			'counteringPlayerDisplay' => $counteringPlayerDisplay,
			'recounteringPlayer' => $recounteringPlayerId,
			'recounteringPlayerDisplay' => $recounteringPlayerDisplay,
		]);
	}

	/**
	 * Notify the current scores
	 */
	private function notifyScores() {
		$newScores = self::getCollectionFromDb(
			'SELECT player_id, player_score FROM player',
			true
		);
		self::notifyAllPlayers('newScores', '', [
			'newScores' => $newScores,
		]);
	}

	/**
	 * Round the value to 10 (104 => 100, 105 => 110)
	 */
	private function roundToTen($value) {
		return (int) (round($value / 10) * 10);
	}

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
				sprintf(
					self::_('You must bid higher than current bid (%s)'),
					$previousValue
				)
			);
		}
		// Bid must change color if same player
		if ($previousColor == $color && $playerId == $previousBidPlayer) {
			throw new BgaUserException(
				self::_('You must change color to bid higher on yourself')
			);
		}

		// Bid cannot be "all trumps / no trump" if disabled
		if ($color >= 5 && $this->getGameStateValue('hasAllNoTrumps') == 2) {
			throw new BgaUserException(
				'All Trumps / No Trumps disabled for this game'
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
			throw new BgaUserException(self::_('Cannot double on no bid'));
		}
		if ($playerId == $bidPlayerId) {
			throw new BgaUserException(self::_('Cannot double on you own bid'));
		}
		if ($partnerId == $bidPlayerId) {
			throw new BgaUserException(
				self::_('Cannot double on you partner\'s bid')
			);
		}

		// Next player
		self::setGameStateValue('countered', 1);
		self::setGameStateValue('counteringPlayer', $playerId);

		// And notify
		self::notifyAllPlayers(
			'updateBidCoinche',
			clienttranslate('${player_name} doubles !'),
			[
				'player_id' => $playerId,
				'player_name' => self::getCurrentPlayerName(),
			]
		);

		$this->notifyBid();
		$this->gamestate->nextState('nextPlayerBid');
	}

	function nosurcoinche() {
		$this->gamestate->checkPossibleAction('nosurcoinche');
		$playerId = self::getCurrentPlayerId();
		self::notifyAllPlayers(
			'message',
			clienttranslate('${player_name} does not redouble.'),
			[
				'player_id' => $playerId,
				'player_name' => self::getCurrentPlayerName(),
			]
		);
		$this->gamestate->setPlayerNonMultiactive($playerId, 'endBidding');
	}

	function surcoinche() {
		$this->gamestate->checkPossibleAction('surcoinche');

		$playerId = self::getCurrentPlayerId();
		$counteringPlayer = self::getGameStateValue('counteringPlayer');

		// Check that this player can indeed surcoinche
		if ($playerId == $counteringPlayer) {
			throw new BgaUserException(self::_('Cannot redouble on yourself'));
		}
		if ($this->getPartnerIdOfPlayerId($playerId) == $counteringPlayer) {
			throw new BgaUserException(self::_('Cannot redouble on your partner'));
		}

		self::setGameStateValue('countered', 2);
		self::setGameStateValue('recounteringPlayer', $playerId);

		// And notify
		self::notifyAllPlayers(
			'updateBidSurCoinche',
			clienttranslate('${player_name} redoubles !'),
			[
				'player_id' => $playerId,
				'player_name' => self::getCurrentPlayerName(),
			]
		);

		$this->gamestate->nextState('endBidding');
	}

	function playCard($cardId, $wantBelote = false) {
		self::checkAction('playCard');
		$playerId = self::getActivePlayerId();

		// Check Rules
		$this->assertCardPlay($cardId);

		// Check for belote
		$beloteDeclared = self::getGameStateValue('beloteDeclared');
		if ($beloteDeclared || $wantBelote) {
			$cardId1 = self::getGameStateValue('beloteCardId1');
			$cardId2 = self::getGameStateValue('beloteCardId2');
			if ($cardId == $cardId1 || $cardId == $cardId2) {
				// Card is belote, and player want it declared
				$beloteText = 'Belote';
				if (!$beloteDeclared) {
					self::setGameStateValue('beloteDeclared', 1);
					$this->notifyBelote();
				} else {
					$beloteText = 'Rebelote';
				}
				self::notifyAllPlayers(
					'sayBelote',
					clienttranslate('${player_name} says ${belote_text} !'),
					[
						'player_id' => $playerId,
						'player_name' => self::getActivePlayerName(),
						'belote_text' => $beloteText,
					]
				);
			}
		}

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
				'${trick_count}${player_name} plays ${value_displayed} ${color_name}'
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
				'color_name' => $this->colors[$currentCard['type']]['name'],
				'trick_count' => self::getGameStateValue('trickCount'),
			]
		);
		// Next player
		$this->gamestate->nextState('playCard');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state arguments
	////////////

	function argPlayerTurn() {
		// On player's turn, list possible cards
		return [
			'_private' => [
				'active' => [
					'possibleCards' => $this->getPossibleCardsToPlay(
						self::getActivePlayerId()
					),
				],
			],
		];
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Game state actions
	////////////

	function stNewHand() {
		// Take back all cards (from any location => null) to deck
		// Create deck, shuffle it and give 8 initial cards
		$this->cards->moveAllCardsInLocation(null, 'deck');
		$this->cards->shuffle('deck');
		$players = self::loadPlayersBasicInfos();
		foreach ($players as $playerId => $player) {
			$cards = $this->cards->pickCards(8, 'deck', $playerId);
			// Notify player about his cards
			self::notifyPlayer($playerId, 'newHand', '', [
				'cards' => $cards,
			]);

			// Reset trick count
			$sql = "UPDATE player SET player_tricks=0 WHERE player_id='$playerId'";
			self::DbQuery($sql);
		}

		self::setGameStateValue('trumpColor', 0);
		self::setGameStateValue('bid', 0);
		self::setGameStateValue('bidPlayer', 0);
		self::setGameStateValue('countered', 0);
		self::setGameStateValue('counteringPlayer', 0);
		self::setGameStateValue('recounteringPlayer', 0);
		self::setGameStateValue('trickCount', 0);
		self::setGameStateValue('beloteCardId1', 0);
		self::setGameStateValue('beloteCardId2', 0);
		self::setGameStateValue('belotePlayerId', 0);
		self::setGameStateValue('beloteDeclared', 0);
		self::setGameStateValue('dixDeDerPlayerId', 0);
		self::incStat(1, 'numberOfHands', 0);

		$this->activateFirstPlayer();

		$this->notifyBid();

		$this->gamestate->nextState('');
	}

	function stStartBidding() {
		self::setGameStateValue('trumpColor', 0);
		self::setGameStateValue('bid', 0);
		self::setGameStateValue('bidPlayer', 0);
		self::setGameStateValue('countered', 0);
		self::setGameStateValue('passCount', 0);
		$this->gamestate->nextState('');
	}

	function stNextPlayerBid() {
		$countered = self::getGameStateValue('countered');
		if ($countered > 1) {
			// Bid ok, activate 'first' player and start playing
			$this->activateFirstPlayer();
			$this->gamestate->nextState('endBidding');
			return;
		}
		if ($countered > 0) {
			// Countered, propose players to redouble
			$this->gamestate->nextState('waitForRedouble');
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
				$this->activateFirstPlayer();
				$this->gamestate->nextState('newHand');
				return;
			}

			// Bid ok, activate 'first' player and start playing
			$this->activateFirstPlayer();
			$this->gamestate->nextState('endBidding');
			$bid = $this->getGameStateValue('bid');
			$trumpColor = self::getGameStateValue('trumpColor');
			$bidPlayerId = self::getGameStateValue('bidPlayer');
			$players = self::loadPlayersBasicInfos();
			$bidPlayerDisplay = $players[$bidPlayerId]['player_name'] ?? '';
			$this->findAndNotifyBelote();

			self::notifyAllPlayers(
				'allPassWithBid',
				clienttranslate(
					'Everybody passes, ${bid_value} ${color_symbol} for ${player_name}'
				),
				[
					'i18n' => ['trumpColorDisplay', 'bidPlayerDisplay'],
					'player_id' => $bidPlayerId,
					'player_name' => $bidPlayerDisplay,
					'color_symbol' => $trumpColor,
					'bid_value' => $bid,
					'bid' => $bid,
					'trumpColor' => $trumpColor,
				]
			);

			return;
		}

		$playerId = self::activeNextPlayer();
		self::giveExtraTime($playerId);
		$this->gamestate->nextState('nextPlayerBid');
	}

	function stEndBidding() {
		// End bidding, prepare card play
		$this->activateFirstPlayer();
		$this->gamestate->nextState('startPlaying');
		$bid = $this->getGameStateValue('bid');
		$trumpColor = self::getGameStateValue('trumpColor');
		$trumpColorDisplay = $this->colors[$trumpColor]['name'];
		$bidPlayerId = self::getGameStateValue('bidPlayer');
		$players = self::loadPlayersBasicInfos();
		$bidPlayerDisplay = $players[$bidPlayerId]['player_name'] ?? '';
		$countered = self::getGameStateValue('countered');
		$counteringPlayerId = self::getGameStateValue('counteringPlayer');
		$recounteringPlayerId = self::getGameStateValue('recounteringPlayer');
		$this->findAndNotifyBelote();
		self::notifyAllPlayers('endBidding', '', [
			'player_id' => $bidPlayerId,
			'player_name' => $bidPlayerDisplay,
			'color_symbol' => $trumpColor,
			'bidPlayer' => $bidPlayerId,
			'bid_value' => $bid,
			'bid' => $bid,
			'trumpColor' => $trumpColor,
			'trumpColorDisplay' => $trumpColorDisplay,
			'countered' => $countered,
			'counteringPlayer' => $counteringPlayerId,
			'counteringPlayerDisplay' =>
				$players[$counteringPlayerId]['player_name'] ?? '',
			'recounteringPlayer' => $recounteringPlayerId,
			'recounteringPlayerDisplay' =>
				$players[$recounteringPlayerId]['player_name'] ?? '',
		]);
		$this->notifyBid();
		self::incStat(1, 'numberOfHandsPlayed');
		self::incStat(1, 'numberOfHandsTaken', $bidPlayerId);
		if ($countered && $counteringPlayerId) {
			self::incStat(1, 'numberOfCounters');
			self::incStat(1, 'numberOfCounters', $counteringPlayerId);
		}
	}

	function stWaitForRedouble() {
		$counteringPlayerId = self::getGameStateValue('counteringPlayer');
		$players = self::loadPlayersBasicInfos();
		$activePlayers = [];
		foreach ($players as $player) {
			if ($player['player_id'] == $counteringPlayerId) {
				continue;
			}
			if (
				$this->getPartnerIdOfPlayerId($player['player_id']) ==
				$counteringPlayerId
			) {
				continue;
			}
			$activePlayers[] = $player['player_id'];
		}
		$this->gamestate->setPlayersMultiactive(
			$activePlayers,
			'endBidding',
			true
		);
		self::notifyAllPlayers(
			'message',
			clienttranslate('Waiting for redouble'),
			[]
		);
	}

	function stNewTrick() {
		// New trick: active the player who wins the last trick
		// Reset trick color to 0 (= no color)

		self::setGameStateValue('trickColor', 0);
		$this->gamestate->nextState();
	}

	function stNextPlayer() {
		if ($this->cards->countCardInLocation('cardsontable') < 4) {
			// not the end of the trick, just active the next player
			$playerId = self::activeNextPlayer();
			self::giveExtraTime($playerId);
			$this->gamestate->nextState('nextPlayer');
			return;
		}

		// 4 cards played, this is the end of the trick.
		// Look up the winner of this trick.

		$players = self::loadPlayersBasicInfos();
		$cardsOnTable = $this->cards->getCardsInLocation('cardsontable');
		$strongestValue = 0;
		$winningCard = null;
		foreach ($cardsOnTable as $card) {
			$strength = $this->getCardStrength($card);
			if ($strength >= $strongestValue) {
				$winningCard = $card;
				$strongestValue = $strength;
			}
		}
		$trickWinnerId = $winningCard['location_arg']; // Note: location_arg = player who played this card on table

		// Move all cards to "cardswon" of the given player
		$this->cards->moveAllCardsInLocation(
			'cardsontable',
			'cardswon',
			null,
			$trickWinnerId
		);

		// Update player tricks won count
		$tricksWon = 0;
		$sql = "UPDATE player SET player_tricks = player_tricks + 1 WHERE player_id = '$trickWinnerId'";
		self::DbQuery($sql);
		$tricksWon = self::getUniqueValueFromDb(
			"SELECT player_tricks FROM player WHERE player_id='$trickWinnerId'"
		);

		// Trick count
		$trickCount = self::getGameStateValue('trickCount');

		$isLastTrick = $this->cards->countCardInLocation('hand') == 0;

		// Notify
		$message = clienttranslate('${trick_count}${player_name} wins the trick');
		if ($isLastTrick) {
			$message = clienttranslate(
				'${player_name} wins the last trick (+10 pts)'
			);
		}
		self::notifyAllPlayers('trickWin', $message, [
			'player_id' => $trickWinnerId,
			'player_name' => $players[$trickWinnerId]['player_name'],
			'trick_count' => $trickCount,
			'trick_won' => $tricksWon,
		]);

		// Increment trick count
		self::setGameStateValue('trickCount', $trickCount + 1);

		if ($isLastTrick) {
			// End of the hand
			self::setGameStateValue('dixDeDerPlayerId', $trickWinnerId);
			$this->gamestate->nextState('endHand');
		} else {
			// End of the trick
			$this->gamestate->changeActivePlayer($trickWinnerId);
			$this->gamestate->nextState('nextTrick');
		}
	}

	function stEndHand() {
		//// Compute score

		// Score table
		$table = [];

		// Helper for score table
		$tableValue = function ($value, $sign = false, $bold = false) {
			if (empty($value)) {
				return '';
			}
			$cls = 'scoreTableValue' . ($bold ? ' scoreTableValue--bold' : '');
			if ($sign) {
				$value = ($value >= 0 ? '+ ' : '- ') . $value;
			}
			return "<span class=\"$cls\">$value</span>";
		};

		// Cards points
		$cardsPoints = $this->getCardsPoints();

		$bid = $this->getGameStateValue('bid');
		$trumpColor = self::getGameStateValue('trumpColor');
		$countered = self::getGameStateValue('countered');

		// Players informations
		$players = self::loadPlayersBasicInfos();
		$nextPlayer = self::createNextPlayerTable(array_keys($players));
		$player1Id = self::getGameStateValue('firstPlayer');
		$player2Id = $nextPlayer[$player1Id];
		$player3Id = $nextPlayer[$player2Id];
		$player4Id = $nextPlayer[$player3Id];
		$counteringPlayerId = self::getGameStateValue('counteringPlayer');

		// Teams by player id
		$playerIdTeam = [
			$player1Id => 0,
			$player2Id => 1,
			$player3Id => 0,
			$player4Id => 1,
		];

		$table[] = [
			'',
			[
				'str' => 'Team ${first_player_name} and ${third_player_name}',
				'args' => [
					'first_player_name' => $players[$player1Id]['player_name'],
					'third_player_name' => $players[$player3Id]['player_name'],
				],
				'type' => 'header',
			],
			[
				'str' => 'Team ${second_player_name} and ${fourth_player_name}',
				'args' => [
					'second_player_name' => $players[$player2Id]['player_name'],
					'fourth_player_name' => $players[$player4Id]['player_name'],
				],
				'type' => 'header',
			],
		];

		// Belote
		$beloteTeamId = null;
		if (self::getGameStateValue('beloteDeclared')) {
			$belotePlayerId = self::getGameStateValue('belotePlayerId');
			$beloteTeamId = $playerIdTeam[$belotePlayerId];
		}

		// DixDeDer
		$dixDeDerPlayerId = self::getGameStateValue('dixDeDerPlayerId');
		$dixDeDerTeamId = $playerIdTeam[$dixDeDerPlayerId];

		// Current player scores by Id
		$playerScores = self::getCollectionFromDb(
			'SELECT player_id, player_score FROM player',
			true
		);

		// Bidding player and bidding team
		$bidPlayerId = self::getGameStateValue('bidPlayer');
		$bidTeam = $playerIdTeam[$bidPlayerId];
		$defenseTeam = $bidTeam === 1 ? 0 : 1;

		// Team points
		$teamPoints = [
			0 => 0,
			1 => 0,
		];
		// Team scores
		$teamScores = [
			0 => 0,
			1 => 0,
		];

		// Score type: count points, or only the bid value ?
		$doAddPointsToScore = self::getGameStateValue('scoreType') == 1;

		// Compute points based on cards won
		$cards = $this->cards->getCardsInLocation('cardswon');
		foreach ($cards as $card) {
			$playerId = $card['location_arg'];
			$teamId = $playerIdTeam[$playerId];
			$teamPoints[$teamId] += $cardsPoints[$card['type']][$card['type_arg']];
		}

		$table[] = [
			clienttranslate('Points'),
			$tableValue($teamPoints[0]),
			$tableValue($teamPoints[1]),
		];

		// Converts points to a total of 162, if "notrump"/"alltrump" bids
		$arrangeMultiplier = null;
		if ($trumpColor == 5) {
			$arrangeMultiplier = 152 / 248;
		} elseif ($trumpColor == 6) {
			$arrangeMultiplier = 152 / 120;
		}
		if ($arrangeMultiplier > 0) {
			$teamPoints[0] = round($teamPoints[0] * $arrangeMultiplier);
			$teamPoints[1] = round($teamPoints[1] * $arrangeMultiplier);

			$table[] = [
				clienttranslate('Points (based on 152)'),
				$tableValue($teamPoints[0]),
				$tableValue($teamPoints[1]),
			];
		}

		// Adds "dix de der" for last trick
		$teamPoints[$dixDeDerTeamId] += 10;

		$table[] = [
			clienttranslate('Dix de der'),
			$tableValue($dixDeDerTeamId === 0 ? '10' : '', true),
			$tableValue($dixDeDerTeamId === 1 ? '10' : '', true),
		];

		$isCapot = false;

		// If a team scored zero points, it's a "capot", so 250pts
		if ($teamPoints[0] == 0) {
			$teamPoints[1] = 250;
			$isCapot = true;
		} elseif ($teamPoints[1] == 0) {
			$teamPoints[0] = 250;
			$isCapot = true;
		}

		if ($isCapot) {
			$table[] = [
				clienttranslate('Points (capot)'),
				$tableValue($teamPoints[0]),
				$tableValue($teamPoints[1]),
			];
		}

		// Adds "20" for belote
		if ($beloteTeamId !== null) {
			$teamPoints[$beloteTeamId] += 20;
			$table[] = [
				'Belote',
				$tableValue($beloteTeamId === 0 ? '20' : '', true),
				$tableValue($beloteTeamId === 1 ? '20' : '', true),
			];
		}

		$table[] = [
			'<b>' . clienttranslate('Points (total)') . '</b>',
			$tableValue($teamPoints[0], false, true),
			$tableValue($teamPoints[1], false, true),
		];

		$bidDisplay = [
			'str' => clienttranslate('${bid_value} ${color_symbol}'),
			'args' => [
				'color_symbol' => $trumpColor,
				'bid_value' => $bid,
			],
		];

		// Multiplier depends on "countered" or not
		$coincheName = '';
		if ($countered == 2) {
			$multiplier = 4;
			$coincheName = clienttranslate('redoubled');
		} elseif ($countered == 1) {
			$multiplier = 2;
			$coincheName = clienttranslate('doubled');
		} else {
			$multiplier = 1;
		}

		$bidSuccessful = $teamPoints[$bidTeam] >= $bid;
		$diffPoints = $teamPoints[$bidTeam] - $bid;
		if ($diffPoints >= 0) {
			$diffPoints = "+$diffPoints";
		}

		// Check bid success/failure
		if ($bidSuccessful) {
			// Success !

			$resultString = clienttranslate('Bid successful ') . "($diffPoints) !";

			if ($bid == 82) {
				$bid = 80;
			}

			// Bidding team : (bid + points) * coinche_multiplier
			$scoreText = [];
			$scoreText[$bidTeam] = "$bid (" . clienttranslate('bid') . ')';
			$teamScores[$bidTeam] = $bid;
			if ($doAddPointsToScore) {
				$scoreText[$bidTeam] .=
					' + ' .
					$teamPoints[$bidTeam] .
					' (' .
					clienttranslate('points') .
					')';
				$teamScores[$bidTeam] += $teamPoints[$bidTeam];
			} else {
				// Add belote if in mode "bid only"
				if ($beloteTeamId === $bidTeam) {
					$scoreText[$bidTeam] .=
						' + 20 (' . clienttranslate('belote') . ')';
					$teamScores[$bidTeam] += 20;
				}
			}
			if ($multiplier > 1) {
				$scoreText[$bidTeam] =
					'( ' . $scoreText[$bidTeam] . " ) ✖ $multiplier ($coincheName)";
				$teamScores[$bidTeam] *= $multiplier;
			}

			// Defense team : points (if not countered)
			if ($countered) {
				$scoreText[$defenseTeam] = "0 ($coincheName)";
				$teamScores[$defenseTeam] = 0;
			} elseif ($doAddPointsToScore) {
				$scoreText[$defenseTeam] =
					$teamPoints[$defenseTeam] .
					' (' .
					clienttranslate('points') .
					')';
				$teamScores[$defenseTeam] = $teamPoints[$defenseTeam];
			} else {
				$scoreText[$defenseTeam] = 0;
				$teamScores[$defenseTeam] = 0;
			}

			if (!$doAddPointsToScore) {
				// Add belote if in mode "bid only", even when losing
				if ($beloteTeamId === $defenseTeam) {
					$scoreText[$defenseTeam] .=
						' + 20 (' . clienttranslate('belote') . ')';
					$teamScores[$defenseTeam] += 20;
				}
			}

			$table[] = [
				$bidDisplay,
				$bidTeam === 0 ? $resultString : '',
				$bidTeam === 1 ? $resultString : '',
			];
			$table[] = [
				clienttranslate('Score count'),
				$scoreText[0],
				$scoreText[1],
			];

			self::notifyAllPlayers(
				'lastScoreSummary',
				clienttranslate(
					'Bid successful, <b>${bidTeamPoints}</b> to <b>${defenseTeamPoints}</b> !'
				),
				[
					'bidTeamPoints' => $teamPoints[$bidTeam],
					'defenseTeamPoints' => $teamPoints[$defenseTeam],
				]
			);

			self::incStat(1, 'numberOfBidSucceeded', $bidPlayerId);
		} else {
			// Failure !

			$resultString = clienttranslate('Bid fails ') . "($diffPoints) !";

			if ($bid == 82) {
				$bid = 80;
			}

			// Bidding team : 0 + belote
			$scoreText = [];
			$scoreText[$bidTeam] = '0';
			$teamScores[$bidTeam] = 0;
			if ($beloteTeamId === $bidTeam) {
				$scoreText[$bidTeam] .= ' + 20 (belote)';
				$teamScores[$bidTeam] += 20;
			}

			// Defense team : (162 + bid + belote) * coinche_multiplier
			$teamScores[$defenseTeam] = $bid;
			$scoreText[$defenseTeam] = "$bid (" . clienttranslate('bid') . ')';
			if ($doAddPointsToScore) {
				$teamScores[$defenseTeam] += 162;
				$scoreText[$defenseTeam] .=
					' + 162 (' . clienttranslate('points') . ')';
			}
			if ($beloteTeamId === $defenseTeam) {
				$scoreText[$defenseTeam] .= ' + 20 (belote)';
				$teamScores[$defenseTeam] += 20;
			}
			if ($multiplier > 1) {
				$scoreText[$defenseTeam] =
					'( ' .
					$scoreText[$defenseTeam] .
					" ) ✖ $multiplier ($coincheName)";
				$teamScores[$defenseTeam] *= $multiplier;
			}
			$table[] = [
				$bidDisplay,
				$bidTeam === 0 ? $resultString : '',
				$bidTeam === 1 ? $resultString : '',
			];
			$table[] = [
				clienttranslate('Score count'),
				$scoreText[0],
				$scoreText[1],
			];

			self::notifyAllPlayers(
				'lastScoreSummary',
				clienttranslate(
					'Bid fails, <b>${bidTeamPoints}</b> to <b>${defenseTeamPoints}</b> !'
				),
				[
					'bidTeamPoints' => $teamPoints[$bidTeam],
					'defenseTeamPoints' => $teamPoints[$defenseTeam],
				]
			);

			self::incStat(1, 'numberOfBidsFailed', $bidPlayerId);
			if ($countered) {
				self::incStat(1, 'numberOfCountersSuccess', $counteringPlayerId);
			}
		}

		// Apply team scores to player
		foreach ($players as $playerId => $player) {
			$points = $teamScores[$playerIdTeam[$playerId]];
			$playerScores[$playerId] += $points;
			$newScore = $playerScores[$playerId];

			// Update score in db
			if ($points != 0) {
				$sql = "UPDATE player SET player_score = $newScore WHERE player_id = '$playerId'";
				self::DbQuery($sql);
			}

			// Notify score by player
			if ($points > 0) {
				$notifyMessage = clienttranslate(
					'${player_name} scores ${points} points'
				);
			} else {
				$notifyMessage = clienttranslate('${player_name} scores no point');
			}
			self::notifyAllPlayers('message', $notifyMessage, [
				'player_id' => $playerId,
				'player_name' => $player['player_name'],
				'points' => $points,
			]);
		}

		$table[] = [
			'<b>' . clienttranslate('Score') . '</b>',
			$tableValue($teamScores[0], true, true),
			$tableValue($teamScores[1], true, true),
		];

		// Notify score info
		$this->notifyScores();

		// Notify the table score
		$this->notifyAllPlayers('scoreTable', '', [
			'title' => $resultString,
			'table' => $table,
			'bidSuccessful' => $bidSuccessful,
		]);

		// Check if end of game (score must be strictly higher than maxScore)
		$maxScore = $this->getMaxScore();
		foreach ($playerScores as $playerId => $score) {
			if ($score > $maxScore) {
				$this->gamestate->nextState('endGame');
				return;
			}
		}

		$this->setNextFirstPlayer();

		$this->gamestate->nextState('nextHand');
	}

	//////////////////////////////////////////////////////////////////////////////
	//////////// Zombie
	////////////

	/*
	 * zombieTurn:
	 *
	 * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
	 * You can do whatever you want in order to make sure the turn of this player ends appropriately
	 * (ex: pass).
	 *
	 * Important: your zombie code will be called when the player leaves the game. This action is triggered
	 * from the main site and propagated to the gameserver from a server, not from a browser.
	 * As a consequence, there is no current player associated to this action. In your zombieTurn function,
	 * you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message.
	 */
	function zombieTurn($state, $activePlayer) {
		$statename = $state['name'];

		if ($state['type'] == 'activeplayer') {
			switch ($statename) {
				case 'playerBid':
					// Always pass
					$this->pass();
					return;

				case 'playerTurn':
					// Loop the player hand, stopping at the first card which can be played
					$playerCards = $this->cards->getCardsInLocation(
						'hand',
						$activePlayer
					);
					foreach ($playerCards as $playerCard) {
						try {
							$this->assertCardPlay($playerCard['id']);
						} catch (\Exception $e) {
							continue;
						}
						break;
					}
					$this->playCard($playerCard['id']);
					return;
			}
		}

		if ($state['type'] === 'multipleactiveplayer') {
			if ($statename === 'waitForRedouble') {
				$this->nosurcoinche();
				return;
			}

			// Make sure player is in a non blocking status for role turn
			$this->gamestate->setPlayerNonMultiactive($active_player, '');

			return;
		}

		throw new feException(
			'Zombie mode not supported at this game state: ' . $statename // NOI18N
		);
	}

	///////////////////////////////////////////////////////////////////////////////////:
	////////// DB upgrade
	//////////

	/*
	 * upgradeTableDb:
	 *
	 * You don't have to care about this until your game has been published on BGA.
	 * Once your game is on BGA, this method is called everytime the system detects a game running with your old
	 * Database scheme.
	 * In this case, if you change your Database scheme, you just have to apply the needed changes in order to
	 * update the game database and allow the game to continue to run with your new version.
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
