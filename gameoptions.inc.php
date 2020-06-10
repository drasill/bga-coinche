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
 * gameoptions.inc.php
 *
 * Coinche game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in coinche.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = [
	100 => [
		'name' => totranslate('Game length'),
		'values' => [
			1 => ['name' => totranslate('Classic (2000 points)')],
			2 => ['name' => totranslate('Half-game (1000 points)')],
		],
	],
	101 => [
		'name' => totranslate('Score type'),
		'values' => [
			1 => ['name' => totranslate('Points + Bid')],
			2 => ['name' => totranslate('Bid only')],
		],
	],
	102 => [
		'name' => totranslate('Teams'),
		'values' => [
			1 => [
				'name' => totranslate('By table order (1rst/3rd versus 2nd/4th)'),
			],
			2 => [
				'name' => totranslate('By table order (1rst/2nd versus 3rd/4th)'),
			],
			3 => [
				'name' => totranslate('By table order (1rst/4th versus 2nd/3rd)'),
			],
			4 => ['name' => totranslate('At random')],
		],
		'default' => 1,
	],
	103 => [
		'name' => totranslate('All trumps / No trumps'),
		'values' => [
			1 => [
				'name' => totranslate('Enabled'),
			],
			2 => [
				'name' => totranslate('Disabled'),
				'tmdisplay' => totranslate('Without All trumps / No trumps'),
			],
		],
		'default' => 1,
	],
];

$game_preferences = [
	100 => [
		'name' => totranslate('Turn order'),
		'needReload' => false,
		'values' => [
			1 => ['name' => totranslate('Clockwise')],
			2 => ['name' => totranslate('Counterclockwise')],
		],
	],
	101 => [
		'name' => totranslate('Confirm Bid'),
		'needReload' => false,
		'values' => [
			1 => ['name' => totranslate('No confirmation')],
			2 => ['name' => totranslate('Bid confirmation')],
		],
	],
	102 => [
		'name' => totranslate('Cards style'),
		'needReload' => false,
		'values' => [
			1 => ['name' => totranslate('French')],
			2 => ['name' => totranslate('English')],
			3 => ['name' => 'Snap'],
		],
	],
];
