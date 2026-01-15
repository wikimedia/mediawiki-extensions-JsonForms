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
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class JsonForms {

	/** @var int */
	public static $queryLimit = 500;

	public static function initialize() {
	}

	/**
	 * @param string $titleText
	 * @return array
	 */
	public static function getJsonSchema( $titleText ) {
		$title = TitleClass::newFromText( $titleText );

		if ( !$title || !$title->isKnown() ) {
			return [];
		}

		$text = self::getArticleContent( $title );

		if ( !$text ) {
			return [];
		}

		return json_decode( $text, true );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return WikiPage|null
	 */
	public static function getWikiPage( $title ) {
		if ( !$title || !$title->canExist() ) {
			return null;
		}
		// MW 1.36+
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		}
		return WikiPage::factory( $title );
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return string|null
	 */
	public static function getArticleContent( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		$content = $wikiPage->getContent( \MediaWiki\Revision\RevisionRecord::RAW );
		if ( !$content ) {
			return null;
		}
		return $content->getNativeData();
	}

	/**
	 * @see specials/SpecialPrefixindex.php -> showPrefixChunk
	 * @param string $prefix
	 * @param int $namespace
	 * @return array
	 */
	public static function getPagesWithPrefix( $prefix, $namespace = NS_MAIN ) {
		$dbr = self::getDB( DB_REPLICA );

		$conds = [
			'page_namespace'   => $namespace,
			'page_is_redirect' => 0,
		];

		if ( !empty( $prefix )  ) {
			$conds[] = 'page_title ' . $dbr->buildLike( $prefix, $dbr->anyString() );
		}

		$options = [
			'LIMIT' => self::$queryLimit,
			'ORDER BY' => 'page_title',
			'USE INDEX' => version_compare( MW_VERSION, '1.36', '<' )
				? 'name_title'
				: 'page_name_title',
		];

		$res = $dbr->select(
			'page',
			[ 'page_namespace', 'page_title', 'page_id' ],
			$conds,
			__METHOD__,
			$options
		);

		if ( !$res->numRows() ) {
			return [];
		}

		$ret = [];
		foreach ( $res as $row ) {
			$title = TitleClass::newFromRow( $row );
			if ( $title && $title->isKnown() ) {
				$ret[] = $title;
			}
		}

		return $ret;
	}

	/**
	 * @param int $db
	 * @return \Wikimedia\Rdbms\DBConnRef
	 */
	public static function getDB( $db ) {
		if ( !method_exists( MediaWikiServices::class, 'getConnectionProvider' ) ) {
			// @see https://gerrit.wikimedia.org/r/c/mediawiki/extensions/PageEncryption/+/1038754/comment/4ccfc553_58a41db8/
			return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( $db );
		}
		$connectionProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		switch ( $db ) {
			case DB_PRIMARY:
				return $connectionProvider->getPrimaryDatabase();
			case DB_REPLICA:
			default:
				return $connectionProvider->getReplicaDatabase();
		}
	}

	/**
	 * @param Title|MediaWiki\Title\Title $title
	 * @return string|null
	 */
	public static function getWikipageContent( $title ) {
		$wikiPage = self::getWikiPage( $title );
		if ( !$wikiPage ) {
			return null;
		}
		$content = $wikiPage->getContent( \MediaWiki\Revision\RevisionRecord::RAW );
		if ( !$content ) {
			return null;
		}
		return $content->getNativeData();
	}

	/**
	 * @param Title $title
	 * @param array slots
	 * @param array &$errors []
	 * @return
	 */
	public static function traverseSchema( array $schema, callable $callback ): array {
		$it = new RecursiveIteratorIterator(
			new RecursiveArrayIterator( $schema ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $it as $key => $value ) {
			$parent =& $schema;
			for ( $depth = 0; $depth < $it->getDepth(); $depth++ ) {
				$parent =& $parent[ $it->getSubIterator( $depth )->key() ];
			}

			$callback( $parent, $key, $value );
		}

		return $schema;
	}

	/**
	 * @param Title $title
	 * @param array slots
	 * @param array &$errors []
	 * @return
	 */
	public static function importRevision( $title, $slots, &$errors = [] ) {
		$services = MediaWikiServices::getInstance();
		$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );

		$wikiPage = new WikiPage( $title );
		if ( !$wikiPage ) {
			$errors[] = 'cannot create wikipage';
			return false;
		}

		$slotsData = [];
		foreach ( $slots as $value ) {
			$slotsData[$value['role']] = $value;
		}

		if ( !array_key_exists( SlotRecord::MAIN, $slotsData ) ) {
			$slotsData = array_merge( [ SlotRecord::MAIN => [
				'model' => 'wikitext',
				'text' => ''
			] ], $slotsData );
		}

		$oldRevisionRecord = $wikiPage->getRevisionRecord();
		$slotRoleRegistry = $services->getSlotRoleRegistry();
		$contentHandlerFactory = $services->getContentHandlerFactory();
		$contentModels = $contentHandlerFactory->getContentModels();

		$revision = new WikiRevision();
		$revision->setTitle( $title );

		// $content = $this->makeContent( $title, $revId, $revisionInfo );
		// $revision->setContent( SlotRecord::MAIN, $content );
		foreach ( $slotsData as $role => $value ) {
			if ( empty( $value['text'] ) && $role !== SlotRecord::MAIN ) {
				continue;
			}

			if ( !empty( $value['model'] ) && in_array( $value['model'], $contentModels ) ) {
				$modelId = $value['model'];

			} elseif ( $slotRoleRegistry->getRoleHandler( $role ) ) {
		 	   $modelId = $slotRoleRegistry->getRoleHandler( $role )->getDefaultModel( $title );

			} elseif ( $oldRevisionRecord !== null && $oldRevisionRecord->hasSlot( $role ) ) {
    			$modelId = $oldRevisionRecord->getSlot( $role )
					->getContent()
					->getContentHandler()
					->getModelID();
			} else {
				$modelId = CONTENT_MODEL_WIKITEXT;
			}

			if ( !isset( $modelId ) ) {
				$errors[] = "cannot determine content model for role $role";
				continue;
			}

			$content = ContentHandler::makeContent( $value['text'], $title, $modelId );
			$revision->setContent( $role, $content );
		}

		return $revision->importOldRevision();
	}
}
