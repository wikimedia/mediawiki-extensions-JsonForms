export default {
	validation: 'onsubmit', // onsubmit, always
	template: 'default',
	max_depth: 16,
	path_separator: '.',
	default_additional_properties: false,
	use_lazy_properties: 'threshold', // never, always, threshold
	lazy_properties_threshold: 6,
	remove_empty_properties: false,
	remove_false_properties: false,
	required_evaluates_non_empty: false,
	debug: false,

	//after the editor has been initialized and
	// before is ready
	onInit: function (jsonFormsInstance, editor) {
		editor.on('ready', () => {
			// update editor with new config
			// const config = {}
			// jsonFormsInstance.createEditor( jsonFormsInstance.el, { ...jsonFormsInstance.config, ...config } );
		});

		editor.on('change', () => {});

		// subeditor access
		const editorAB = editor.watch('root.a.b', (editor) => {
			// do something when the value changes
			// console.log('^^editorAB', editor.getValue());
		});

		// another way to register an event, this will fire
		// for each editor in the page, including nested editors
		// in the same form
		const context = this;
		const onPagedLayoutSetPage = function (thisJsonFormsInstance, editor, eventData) {
			// console.log('event onPagedLayoutSetPage via event registration', editor, eventData);
		};
		jsonFormsInstance.registerEvent(
			context,
			'pagedLayoutSetPage',
			onPagedLayoutSetPage,
		);
	},
	callbacks: {
		enum_providers: {
			wikiList: function () {
				function parseBulletList(content) {
					const regex = /^\*\s*(.+)$/gm;
					const items = [];
					let match;

					while ((match = regex.exec(content)) !== null) {
						items.push(match[1]);
					}

					return items;
				}

				const cache = {};
				return {
					source: (jseditor, { item, watched }) => {
						if (
							!jseditor.schema['x-data'] ||
							!jseditor.schema['x-data'].article
						) {
							console.log(
								'A key "article" must be specified in an object with key "x-data" in the enum schema',
							);

							return [];
						}

						const pageTitle = jseditor.schema['x-data'].article;
						if (cache[pageTitle]) {
							return cache[pageTitle];
						}

						return jseditor.fetchArticleContent(pageTitle).then((content) => {
							cache[pageTitle] = parseBulletList(content);
							return cache[pageTitle];
						});
					},
					filter: (jseditor, { item, watched }) => {
						return true;
					},
					title: (jseditor, { item, watched }) => item.text,
					value: (jseditor, { item, watched }) => item.value,
				};
			},

			jsonSchemas: function () {
				let cache = null;
				let pending = null;

				return {
					source: (jseditor, { item, watched }) => {
						if (cache) return cache;
						if (pending) return pending;

						const api = new mw.Api();

						pending = api
							.get({
								action: 'query',
								list: 'allpages',
								apnamespace: 2100,
								aplimit: 'max',
								formatversion: 2,
							})
							.then((res) => {
								cache = res.query.allpages.map((page) => {
									const titleObj = new mw.Title(page.title);
									const baseTitle = titleObj.getMainText();
									return {
										text: baseTitle,
										value: baseTitle,
									};
								});
								pending = null;
								return cache;
							});

						return pending;
					},
					filter: (jseditor, { item, watched }) => {
						return true;
					},
					title: (jseditor, { item, watched }) => item.text,
					value: (jseditor, { item, watched }) => item.value,
				};
			},
		},
		autocomplete_providers: {
			jsonSchemas: function () {
				let cache = null;
				let pending = null;

				return {
					search: async (jseditor_editor, input) => {
						// If we have cached results, filter them based on input
						if (cache) {
							if (!input) return cache;
							const lowerInput = input.toLowerCase();
							return cache.filter((item) =>
								item.text.toLowerCase().includes(lowerInput),
							);
						}

						if (pending) {
							return pending;
						}

						const api = new mw.Api();
						pending = api
							.get({
								action: 'query',
								list: 'allpages',
								apnamespace: 2100,
								aplimit: 'max',
								formatversion: 2,
							})
							.then((res) => {
								cache = res.query.allpages.map((page) => {
									const titleObj = new mw.Title(page.title);
									const baseTitle = titleObj.getMainText();
									return {
										text: baseTitle,
										value: baseTitle,
									};
								});
								pending = null;

								// Filter after caching if input exists
								if (input) {
									const lowerInput = input.toLowerCase();
									return cache.filter((item) =>
										item.text.toLowerCase().includes(lowerInput),
									);
								}
								return cache;
							});

						return pending;
					},
					getResultValue: (jseditor_editor, result) => result.value,
					renderResult: (jseditor_editor, result, props) => result.text,
				};
			},
		},

		// must be a function with a "compile" method
		template: {},

		actions: {
			submit: function (editor) {
				const jsonEditor = editor.jsoneditor;
				const jsonForm = jsonEditor.options.jsonFormsInstance;
				const validation = jsonEditor.validate();
				if (!validation.length) {
					editor.disable();
					const optionsEditor = jsonForm.getEditor('root.form.options');
					jsonForm
						.submitForm(jsonEditor, optionsEditor)
						.then(() => editor.enable())
						.catch((err) => {
							console.error('API error:', err);
							editor.enable();
						});
				} else {
					JsonForms.Alert('there are errors');
				}
			},
		},

		// currently not used
		upload: {},

		// an object of converter names returning a convertTo and convertFrom functions
		// the converter name must then be used in the schema using x-value-converter: [converter name]
		converters: {},

		preprocessData: function (editor, data) {
			return data;
		},

		postprocessData: function (editor, data) {
			return data;
		},

		events: {
			initialized: function (editor, eventData) {
				// console.log('event initialized via options', editor, eventData);
			},
			ready: function (editor, eventData) {
				// console.log('event ready via options', editor, eventData);
			},
			change: function (editor, eventData) {
				// console.log('event change via options', editor, eventData);
			},
			buildComplete: function (editor, eventData) {
				// console.log('event buildComplete via options', editor, eventData);
			},
			pagedLayoutSetPage: function (editor, eventData) {
				// console.log('event pagedLayoutSetPage via options', editor, eventData);
			},
			fancyTreeSelectItem: function (editor, eventData) {
				// console.log('event fancyTreeSelectItem via options', editor, eventData);
			},
			fancyTreeClickItem: function (editor, eventData) {
				// console.log('event fancyTreeClickItem via options', editor, eventData);
			},
		},
	},
};

