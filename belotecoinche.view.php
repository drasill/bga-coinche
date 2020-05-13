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
				'PLAYER_AVATAR_URL_32' => $this->getPlayerAvatar($players[$playerId], '32'),
				'PLAYER_AVATAR_URL_50' => $this->getPlayerAvatar($players[$playerId], '50'),
				'PLAYER_AVATAR_URL_184' => $this->getPlayerAvatar($players[$playerId], '184'),
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

	private function getPlayerAvatar($player, $size) {
		$avatarPlayerId = (string)$player['player_id'];
		$avatarPlayerId = '86769394'; // DEBUG
		$avatarUrl = sprintf('https://x.boardgamearena.net/data/avatar/%s/%s/%s/%s_%s.jpg?h=%s',
			'0',
			substr($avatarPlayerId, 0, 2),
			substr($avatarPlayerId, 0, 5),
			$avatarPlayerId,
			$size,
			$player['player_avatar']
		);
		return $avatarUrl;
	}
}
