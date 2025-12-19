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
 * @ingroup extensions
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2021-2024, https://wikisphere.org
 */

use MediaWiki\Extension\JsonForms\Aliases\Html as HtmlClass;
use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;

class SpecialJsonFormsDemo extends SpecialPage {

	/** @inheritDoc */
	public function __construct() {
		$listed = true;

		// https://www.mediawiki.org/wiki/Manual:Special_pages
		parent::__construct( 'JsonFormsDemo', '', $listed );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( $this->getRobotPolicy() );

		$user = $this->getUser();

		$securityLevel = $this->getLoginSecurityLevel();

		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->addHelpLink( 'Extension:JsonForms' );

		$out->addModules( 'ext.JsonForms.editor' );
		$context = RequestContext::getMain();

		// form descriptor
		// if ( !$par ) {
		// 	exit;
		// }

		$par = 'CreateArticle';
		$title_ = TitleClass::newFromText( 'JsonForm:' . $par );

		if ( !$title_ || !$title_->isKnown() ) {
			echo 'enter a valid form descriptor';
			exit;
		}

		$formDescriptor = \JsonForms::getJsonSchema( 'JsonForm:' . $par  );
		if ( empty( $formDescriptor ) ) {
			echo 'enter a valid form descriptor';
			exit;
		}

		$schemas = [];
		$jsonSchema = [];
		$schemaName = null;
		if ( !empty( $formDescriptor['schema'] ) ) {
			$schemaName = $formDescriptor['schema'];
			$jsonSchema = \JsonForms::getJsonSchema( 'JsonSchema:' . $schemaName  );

			if ( empty( $formDescriptor ) ) {
				echo 'invalid schema in form descriptor';
				exit;
			}

		} else {
			$schemas = \JsonForms::getPagesWithPrefix( null, NS_JSONSCHEMA );
			$schemas = array_map( static function ( $x ) { return $x->getText(); }, $schemas);
		}

		$metaSchema =  \JsonForms::getJsonSchema( 'JsonSchema:MetaSchema' );

		$out->addJsConfigVars( [
			'jsonforms-schemas' => $schemas,
			'jsonforms-metaschema' => $metaSchema
		] );

		// @see SpecialRecentChanges
		$loadingContainer = HtmlClass::rawElement(
			'div',
			[ 'class' => 'rcfilters-head mw-rcfilters-head', 'id' => 'mw-rcfilters-spinner-wrapper', 'style' => 'position: relative' ],
			HtmlClass::rawElement(
				'div',
				[ 'class' => 'initb mw-rcfilters-spinner', 'style' => 'margin-top: auto; top: 25%' ],
				HtmlClass::element(
					'div',
					[ 'class' => 'inita mw-rcfilters-spinner-bounce' ],
				)
			)
		);

		$loadingPlaceholder = HtmlClass::rawElement(
			'div',
			[ 'class' => 'JsonFormsFormWrapperPlaceholder' ],
			$this->msg( 'jsonforms-loading-placeholder' )->text()
		);

		$out->addHTML( HtmlClass::rawElement( 'div', [
				'data-form-data' => json_encode( [
					'formDescriptor' => $formDescriptor,
					'schema' => $jsonSchema,
					'schemaName' => $schemaName
				] ),
				'class' => 'JsonFormsForm JsonFormsFormWrapper'
			], $loadingContainer . $loadingPlaceholder )
		);
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'jsonforms';
	}

}
