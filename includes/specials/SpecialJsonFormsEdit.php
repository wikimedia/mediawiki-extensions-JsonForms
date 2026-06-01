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
 * @copyright Copyright ©2026, https://wikisphere.org
 */

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;

class SpecialJsonFormsEdit extends SpecialPage {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$listed = false;

		// https://www.mediawiki.org/wiki/Manual:Special_pages
		parent::__construct( "JsonFormsEdit", "", $listed );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();

		$out->addModuleStyles( "mediawiki.special" );
		$this->addHelpLink( "Extension:JsonForms" );

		$out->enableOOUI();

		if ( !$par ) {
			return $this->printError( $out, "jsonforms-special-edit-notitle" );
		}

		$editTitle = TitleClass::newFromText( $par );

		if ( !$editTitle || !$editTitle->isKnown() ) {
			return $this->printError(
				$out,
				"jsonforms-special-edit-title-unknown",
			);
		}

		$out->addWikiMsg( "jsonforms-special-edit-message" );

		$jsonForm = \JsonForms::getSourceSchema(
			"EditDataUI",
			"JsonSchema/Core",
		);

		$startVal = new stdClass();
		$startVal->form = new stdClass();
		$startVal->form->schema = new stdClass();
		$startVal->form->schema->selectedSchema = new stdClass();

		$wikiPage = \JsonForms::getWikiPage( $editTitle );

		$metadata = \JsonForms::getMetadata( $wikiPage );

		// Check if metadata exists and has slots property (as object)
		if (
			$metadata &&
			isset( $metadata->slots ) &&
			is_object( $metadata->slots )
		) {
			// can be either SLOT_ROLE_JSONFORMS_DATA or main
			foreach ( $metadata->slots as $role => $value ) {
				if ( isset( $value->schema ) ) {
					$content = \JsonForms::getSlotContent( $wikiPage, $role );
					$startVal->form->schema->selectedSchema->schemaName =
						$value->schema;
					$startVal->form->schema->selectedSchema->editor = json_decode(
						$content,
						false,
					);

					$metadataKeys = [
						"show_infobox" => "showInfobox",
						"infobox_template" => "infoboxTemplate",
					];

					foreach ( $metadataKeys as $key => $val ) {
						if ( !empty( $value->{$val} ) ) {
							if ( !isset( $startVal->form->options ) ) {
								$startVal->form->options = new stdClass();
							}
							$startVal->form->options->{$key} = $value->{$val};
						}
					}
					break;
				}
			}
		}

		$formData = new stdClass();
		$formData->schema = $jsonForm;
		$formData->editorOptions = "MediaWiki:DefaultEditorOptions";
		$formData->editorScript = "MediaWiki:DefaultEditorScript";
		$formData->metadata = $metadata;
		$formData->editTitle = $editTitle->getFullText();

		if ( !empty( (array)$startVal->form ) ) {
			$formData->startval = $startVal;
		}

		$formData = \JsonForms::prepareFormData( $out, $formData );

		$res_ = \JsonForms::getJsonFormHtml( $formData, [ "width" => "auto" ] );

		if ( !$res_->ok ) {
			return $this->printError( $out, $res_->error );
		}

		$html = $res_->value;

		$out->addModules( "ext.JsonForms.editSchema" );
		\JsonForms::addJsConfigVars( $out );
		$out->addHTML( $html );
	}

	/**
	 * @param Output $out
	 * @param string $msg
	 */
	private function printError( $out, $msg ) {
		$out->addHTML(
			new \OOUI\MessageWidget( [
				"type" => "error",
				"label" => new \OOUI\HtmlSnippet( $this->msg( $msg )->parse() ),
			] ),
		);
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return "jsonforms";
	}
}
