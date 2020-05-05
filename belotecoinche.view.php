<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BeloteCoinche implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

require_once APP_BASE_PATH . 'view/common/game.view.php';

class view_belotecoinche_belotecoinche extends game_view {
	function getGameName() {
		return 'belotecoinche';
	}

	function build_page($viewArgs) {
		// Get players & players number
		$players = $this->game->loadPlayersBasicInfos();

		/*********** Place your code below:  ************/
		$template = self::getGameName() . '_' . self::getGameName();

		$playerToDir = $this->game->getPlayersToDirection();
		$this->page->begin_block($template, 'player');
		foreach ($playerToDir as $playerId => $dir) {
			$this->page->insert_block('player', array(
				'PLAYER_ID' => $playerId,
				'PLAYER_NAME' => $players[$playerId]['player_name'],
				'PLAYER_COLOR' => $players[$playerId]['player_color'],
				'DIR' => $dir,
			));
		}

		// Some translations
		$this->tpl['MY_HAND'] = self::_('My hand');
		$this->tpl['BID_OR'] = self::_('Bid or');
		$this->tpl['PASS'] = self::_('Pass');
		$this->tpl['COUNTER'] = self::_('Counter');

		/*********** Do not change anything below this line  ************/
	}
}
