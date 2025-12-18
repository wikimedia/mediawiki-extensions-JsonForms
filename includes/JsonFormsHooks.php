<?php

/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2025, https://wikisphere.org
 */

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;


class JsonFormsHooks {

	/**
	 * @param array $credits
	 * @return void
	 */
	public static function initExtension( $credits = [] ) {
	}

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
	}

	/**
	 * @param DatabaseUpdater|null $updater
	 */
	public static function onLoadExtensionSchemaUpdates( ?DatabaseUpdater $updater = null ) {
	}

	/**
	 * @param OutputPage $outputPage
	 * @param Skin $skin
	 * @return void
	 */
	public static function onBeforePageDisplay( OutputPage $outputPage, Skin $skin ) {
	}

	/**
	 * @param Title|Mediawiki\Title\Title &$title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki
	 * @return void
	 */
	public static function onBeforeInitialize(
		&$title,
		$unused,
		OutputPage $output,
		User $user,
		WebRequest $request,
		/* MediaWiki|MediaWiki\Actions\ActionEntryPoint */ $mediaWiki
	) {
		\JsonForms::initialize();
	}

	/**
	 * @param Skin $skin
	 * @param array &$bar
	 * @return void
	 */
	public static function onSkinBuildSidebar( $skin, &$bar ) {
	}

	/**
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 * @return void
	 */
	public static function onResourceLoaderGetConfigVars( &$vars, $skin, $config ) {
	}
}
