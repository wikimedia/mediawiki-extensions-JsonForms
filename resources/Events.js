// use IIFE, this ensure name is scoped

/* eslint-disable no-unused-vars */
/* eslint-disable no-case-declarations */

( function () {
	function Events() {}

	Events.prototype.initialized = function ( jsonFormsInstance, editor, eventData ) {
		// console.log('event initialized via module', editor, eventData);
	};
	Events.prototype.ready = function ( jsonFormsInstance, editor, eventData ) {
		// console.log('event ready via module', editor, eventData);
	};
	Events.prototype.change = function ( jsonFormsInstance, editor, eventData ) {
		// console.log('event change via module', editor, eventData);
	};
	Events.prototype.buildComplete = function ( jsonFormsInstance, editor, eventData ) {
		// console.log('event buildComplete via module', editor, eventData);
	};
	Events.prototype.pagedLayoutSetPage = function ( jsonFormsInstance, editor, eventData ) {
		// console.log('event pagedLayoutSetPage via module', editor, eventData);
	};
	Events.prototype.fancyTreeSelectItem = function ( jsonFormsInstance, editor, eventData ) {
		// console.log('event fancyTreeSelectItem via module', editor, eventData);
	};
	Events.prototype.fancyTreeClickItem = function ( jsonFormsInstance, editor, eventData ) {
		// console.log('event fancyTreeClickItem via module', editor, eventData);
	};
	// eslint-disable-next-line no-undef
	JsonForms.Events = Events;
}() );
