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

$this->colors = array(
	1 => array('name' => clienttranslate('spade'), 'nametr' => self::_('spade')),
	2 => array('name' => clienttranslate('heart'), 'nametr' => self::_('heart')),
	3 => array('name' => clienttranslate('club'), 'nametr' => self::_('club')),
	4 => array(
		'name' => clienttranslate('diamond'),
		'nametr' => self::_('diamond'),
	),
);

$this->values_label = array(
	7 => '7',
	8 => '8',
	9 => '9',
	10 => '10',
	11 => clienttranslate('J'),
	12 => clienttranslate('Q'),
	13 => clienttranslate('K'),
	14 => clienttranslate('A'),
);
