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
 * along with JsonForms. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2025, https://wikisphere.org
 */

JsonForms = function () {
	let MetaSchema;

	function buildFormSchema(targetSchema, descriptor) {
		const result = structuredClone(targetSchema);
		result.properties.options.properties = {};

		for (const [key, field] of Object.entries(
			targetSchema.properties.options.properties,
		)) {
			const keyMap = {
				categories: 'edit_categories',
				wikitext: 'edit_wikitext',
				slot: 'edit_slot',
				content_model: 'edit_content_model',
			};

			if (keyMap[key] && !descriptor[keyMap[key]]) {
				result.properties.options.required =
					result.properties.options.required.filter((k) => k !== key);
				continue;
			}

			result.properties.options.properties[key] = field;
		}

		// remove schema select if schema is defined
		if (descriptor.schema) {
			delete result.properties.schema.properties.schema;
		}

		return result;
	}

	function createEditor(config) {
		$(config.el).html('');

		const editor = new JSONEditor(config.el, {
			theme: 'oojs',
			schema: config.schema,
			metaSchema: MetaSchema,
			schemaName: config.schemaName,
			uiSchema: config.uiSchema,
			// partialSchema: 'options',
			// show_errors: 'change',
			ajax: true,
			/*
			ajax: function (url, callback) {
				fetch(url, {
					cache: 'no-store',
				})
					.then(function (res) {
						return res.text();
					})
					.then(function (text) {
						const schemaObj = JSON.parse(text);
						callback(schemaObj);
					})
					.catch(function (err) {
						console.error('Failed to load schema:', err);
					});
			},
		*/
		});

		const textarea = $('<textarea>', {
			class: 'form-control',
			id: 'value',
			rows: 12,
			style: 'font-size: 12px; font-family: monospace;',
		});

		$(config.el).append(textarea);

		editor.on('change', () => {
			textarea.val(JSON.stringify(editor.getValue(), null, 2));
		});

		editor.on('ready', () => {});

		return editor;
	}

	function loadSchema(schemaName) {
		if (!schemaName) return Promise.reject('No schema name provided');

		return new Promise((resolve, reject) => {
			fetch(mw.util.getUrl(`JsonSchema:${schemaName}`, { action: 'raw' }), {
				cache: 'no-store',
			})
				.then((res) => res.text())
				.then((text) => {
					try {
						const json = JSON.parse(text);
						resolve(json);
					} catch (error) {
						console.error('Failed to parse schema JSON:', error);
						reject(error);
					}
				})
				.catch((fetchError) => {
					console.error('Failed to fetch schema:', fetchError);
					reject(fetchError);
				});
		});
	}

	function init(el, schemas, metaSchema) {
		MetaSchema = metaSchema;
		const data = $(el).data();

		// console.log('MetaSchema',MetaSchema)

		$(el).html('');

		// console.log('data', data);
		const formDescriptor = data.formData.formDescriptor;
		const schema = data.formData.schema;
		const schemaName = data.formData.schemaName;

		// console.log('formDescriptor', formDescriptor);
		// console.log('schema', schema);

		// const optionsHolder = $(el).append('<div>');
		// const schemaHolder = $(el).append('<div>');

		const Outerschema = {
			title: '',
			type: 'object',
			options: {
				layout: {
					name: 'booklet',
				},
			},
			properties: {
				schema: {
					type: 'object',
					properties: {
						schema: {
							type: 'string',
							enum: ['', ...schemas],
						},
						uischema: {
							type: 'string',
							enum: ['', ...schemas],
						},
						info: {
							type: 'info',
						},
					},
					required: ['schema', 'info'],
				},
				options: {
					type: 'object',
					properties: {
						title: {
							type: 'string',
							options: { input: { name: 'title' } },
						},
						categories: {
							type: 'array',
							items: {
								type: 'string',
								options: { input: { name: 'categorymultiselect' } },
							},
						},
						wikitext: { type: 'string' },
						slot: { type: 'string' },
						content_model: { title: 'content model', type: 'string' },
						summary: { type: 'string', format: 'textarea' },
					},
					required: ['title', 'slot', 'content_model'],
				},
			},
			required: ['options', 'schema'],
		};

		// console.log('formDescriptor', formDescriptor);
		// console.log('Outerschema', Outerschema);

		const editor = createEditor({
			schemaName: 'Form',
			el,
			schema: buildFormSchema(Outerschema, formDescriptor),
		});

		if (schema && Object.keys(schema).length) {
			editor.on('ready', () => {
				editor_ = editor.getEditor('root.schema.info');

				if (editor_) {
					createEditor({ schemaName, el: editor_.container, schema });
				}
			});

			return;
		}

		function reloadSchema() {
			let editor_ = editor.getEditor('root.schema.schema');
			const schemaName = editor_.getValue();

			const schemaEditor = editor.getEditor('root.schema.info');

			editor_ = editor.getEditor('root.schema.uischema');
			const uiSchemaName = editor_.getValue();

			if (uiSchemaName) {
				loadSchema(uiSchemaName).then((uiSchema) => {
					loadSchema(schemaName).then((schema) => {
						createEditor({
							schemaName,
							schema,
							uiSchema,
							el: schemaEditor.container,
						});
					});
				});
			} else {
				loadSchema(schemaName).then((schema) => {
					createEditor({
						schemaName,
						schema,
						el: schemaEditor.container,
					});
				});
			}
		}

		editor.on('ready', () => {
			editor.watch('root.schema.schema', () => {
				reloadSchema();
			});

			editor.watch('root.schema.uischema', () => {
				reloadSchema();
			});
		});
	}

	return { init };
};

$(function () {
	const schemas = mw.config.get('jsonforms-schemas');
	// console.log('schemas', schemas);

	const metaSchema = mw.config.get('jsonforms-metaschema');
	// console.log('metaSchema', metaSchema);

	$('.jsonforms-form-wrapper').each(function (index, el) {
		const webPubCreatorJsonEditor = new JsonForms();
		webPubCreatorJsonEditor.init(el, schemas, metaSchema);
	});
});
