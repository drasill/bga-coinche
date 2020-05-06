<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BeloteCoinche implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

$this->colors = [
	1 => [
		'name' => clienttranslate('spade'),
		'nametr' => self::_('spade'),
	],
	2 => [
		'name' => clienttranslate('heart'),
		'nametr' => self::_('heart'),
	],
	3 => [
		'name' => clienttranslate('club'),
		'nametr' => self::_('club'),
	],
	4 => [
		'name' => clienttranslate('diamond'),
		'nametr' => self::_('diamond'),
	],
	5 => [
		'name' => clienttranslate('alltrump'),
		'nametr' => self::_('alltrump'),
	],
	6 => [
		'name' => clienttranslate('notrump'),
		'nametr' => self::_('notrump'),
	],
];

$this->values_label = [
	7 => '7',
	8 => '8',
	9 => '9',
	10 => '10',
	11 => clienttranslate('J'),
	12 => clienttranslate('Q'),
	13 => clienttranslate('K'),
	14 => clienttranslate('A'),
];
