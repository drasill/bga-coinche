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
 */

require_once APP_BASE_PATH . 'view/common/game.view.php';

class view_coinche_coinche extends game_view {
	function getGameName() {
		return 'coinche';
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
				'PLAYER_AVATAR_URL_32' => $this->getPlayerAvatar(
					$players[$playerId],
					'32'
				),
				'PLAYER_AVATAR_URL_50' => $this->getPlayerAvatar(
					$players[$playerId],
					'50'
				),
				'PLAYER_AVATAR_URL_184' => $this->getPlayerAvatar(
					$players[$playerId],
					'184'
				),
				'DIR' => $dir,
			));
		}

		// Some translations
		$this->tpl['MY_HAND'] = self::_('My hand');
		$this->tpl['BID_OR'] = self::_('Bid or');
		$this->tpl['PASS'] = self::_('Pass');
		$this->tpl['DOUBLE'] = self::_('Double');
		$this->tpl['CONFIRM'] = self::_('Confirm');
		$this->tpl['CANCEL'] = self::_('Cancel');
		$this->tpl['CARDS_STYLE'] = self::_('Cards style');
		$this->tpl['CONFIRM_BIDS'] = self::_('Confirm bids');
		$this->tpl['YOUR_PREFERENCES'] = self::_('Your preferences');

		/*********** Do not change anything below this line  ************/
	}

	private function getPlayerAvatar($player, $size) {
		$avatarPlayerId = (string) $player['player_id'];

		// Zero means "0", otherwise, length of the string from the start
		$lengthMap = [
			8 => [0, 2, 5],
			7 => [0, 1, 4],
			6 => [0, 0, 3],
			5 => [0, 0, 1], // ? no case found
			4 => [0, 0, 1],
			3 => [0, 0, 0],
			2 => [0, 0, 0],
			1 => [0, 0, 0],
		];

		$length = strlen($avatarPlayerId);
		if (!isset($lengthMap[$length])) {
			return null;
		}
		$len0 = $lengthMap[$length][0];
		$len1 = $lengthMap[$length][1];
		$len2 = $lengthMap[$length][2];

		$avatarUrl = sprintf(
			'https://x.boardgamearena.net/data/avatar/%s/%s/%s/%s_%s.jpg?h=%s',
			$len0 === 0 ? '0' : substr($avatarPlayerId, 0, $len0),
			$len1 === 0 ? '0' : substr($avatarPlayerId, 0, $len1),
			$len2 === 0 ? '0' : substr($avatarPlayerId, 0, $len2),
			$avatarPlayerId,
			$size,
			$player['player_avatar']
		);

		return $avatarUrl;
	}
}
