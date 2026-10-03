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

class JsonFormsApiDeleteArticle extends ApiBase {

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$user = $this->getUser();

		\JsonForms::initialize();
		$result = $this->getResult();
		$params = $this->extractRequestParams();
		$context = RequestContext::getMain();

		$data = json_decode( $params['data'], true );
		$title = TitleClass::newFromText( $data['title'] ?? $data['article'] );

		$result_ = null;
		if ( $title && $title->isKnown() ) {
			$reason = 'JsonForms deletion by UI';
			$status = \JsonForms::deleteArticle( $title, $user, $reason );
			if ( $status->isOK() ) {
				$result_ = true;
			}
		}

		$result->addValue( [ $this->getModuleName() ], 'result', json_encode( $result_ ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'data' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=jsonforms-delete-article'
			=> 'apihelp-jsonforms-delete-article-example-1'
		];
	}
}
