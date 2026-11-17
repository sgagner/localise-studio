<?php
/**
 * @package     Com_Localise
 * @subpackage  views
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Localise\Administrator\Helper\InstalledExtensionLanguages;
use Joomla\Component\Localise\Administrator\Model\PackageModel;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('jquery.framework');
HTMLHelper::_('script', 'com_localise/package-files.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);

Factory::getApplication()->getLanguage()->load('com_localise_picker', JPATH_COMPONENT_ADMINISTRATOR);

$this->form->setFieldAttribute('name', 'label', 'COM_LOCALISE_INTERNAL_PACKAGE_NAME');
$this->form->setFieldAttribute('name', 'description', 'COM_LOCALISE_INTERNAL_PACKAGE_NAME_DESC');

$fieldSets = $this->form->getFieldsets();
$ftpSets   = $this->formftp->getFieldsets();

Text::script('COM_LOCALISE_MSG_CONFIRM_PACKAGE_SAVE');
Text::script('COM_LOCALISE_INSTALLED_EXTENSION_NO_FILES');
Text::script('COM_LOCALISE_INSTALLED_EXTENSION_FILE_COUNT');
Text::script('COM_LOCALISE_SOURCE_FILE_CHANGES');
Text::script('COM_LOCALISE_SOURCE_FILE_CONTENT_CHANGES');
Text::script('COM_LOCALISE_SOURCE_FILE_NO_EXPORT');
Text::script('COM_LOCALISE_SOURCE_UNAVAILABLE');
$installedSources = InstalledExtensionLanguages::getSources();
$storedSource = (string) $this->form->getValue('installedsource');

Factory::getDocument()->addScriptDeclaration("
	Joomla.submitbutton = function(task)
	{
		if ((task == 'packagefile.apply' || task == 'packagefile.save') && document.formvalidator.isValid(document.getElementById('localise-package-form')))
		{
			if (confirm(Joomla.JText._('COM_LOCALISE_MSG_CONFIRM_PACKAGE_SAVE')))
			{
				Joomla.submitform(task, document.getElementById('localise-package-form'));
			}
		}
		else if (task == 'packagefile.cancel' || task == 'packagefile.download')
		{
			Joomla.submitform(task, document.getElementById('localise-package-form'));
		}
	}
");

Factory::getDocument()->addScriptDeclaration("
var localiseFileRequest = 0;

function updateTranslationsList(review) {
	var request = ++localiseFileRequest;
	var packagename   = jQuery('#jform_name').val();
	var packageid     = jQuery('#jform_id').val();
	var languagetag   = jQuery('#jform_language').val();
	var source        = jQuery('#localise-installed-extension').val();
	var selected      = jQuery('#jform_translations').val() || [];
	var checkButton   = jQuery('#localise-check-file-changes');
	var token         = '". Session::getFormToken() ."';
	var required_data = JSON.stringify([{
										'installedextension' : source,
										'packageid'   : packageid,
										'packagename' : packagename,
										'languagetag' : languagetag,
										'review'      : review,
										'selected'    : selected
										}]);
	checkButton.prop('disabled', true);
	jQuery('#localise-extension-file-status').text('');
	jQuery('#localise-review-files').trigger('localise:reviewReset');
	jQuery.post('index.php',{
		'option'     : 'com_localise',
		'controller' : 'packagefile',
		'task'       : 'packagefile.updatetranslationslist',
		'format'     : 'raw',
		'data'       : required_data,
		[token]      : '1',
		'dataType'   : 'json'
		})
	.done(function(result, textStatus, jqXHR)
	{
		if (request !== localiseFileRequest)
		{
			return;
		}

		const reply = JSON.parse(result);
		//console.log(reply);

		if (!reply.success && reply.message)
		{
			// Success flag is set to 'false' and main response message given
			// so we can alert it or insert it into some HTML element
			alert(result.message);
		}

		if (reply.messages)
		{
			// All the enqueued messages of the app object can simple be
			// rendered by the respective helper function of Joomla!
			// They will automatically be displayed at the messages section of the template
			Joomla.renderMessages(reply.messages);
		}

		if (reply.data)
		{
			if (reply.data.sourceUnavailable)
			{
				jQuery('#localise-extension-file-status').text(Joomla.Text._('COM_LOCALISE_SOURCE_UNAVAILABLE'));
				return;
			}
			// Here we can access all the data of our response

			if (reply.success)
			{
				if (reply.data.html)
				{
					var updated = jQuery(reply.data.translations).find('select').addBack('select').first();
					if (updated.length)
					{
						var fileCount = updated.find('option:selected').length;
						jQuery('#jform_translations').empty().append(updated.children());
						jQuery('#jform_checkedsourcefiles').val(reply.data.sourceFiles ? JSON.stringify(reply.data.sourceFiles) : '');
						if (review && window.location.hash === '#localise-review-files')
						{
							jQuery('#localise-review-files').trigger('localise:reviewReady', [reply.data.reviewFiles || []]);
						}
						if (source && languagetag)
						{
							var changes = reply.data.fileChanges;
							var status = changes
								? Joomla.Text._('COM_LOCALISE_SOURCE_FILE_CHANGES').replace('%1\$s', changes.new).replace('%2\$s', changes.missing)
								: (fileCount
									? Joomla.Text._('COM_LOCALISE_INSTALLED_EXTENSION_FILE_COUNT').replace('%s', fileCount)
									: Joomla.Text._('COM_LOCALISE_INSTALLED_EXTENSION_NO_FILES'));
							if (changes)
							{
								status += ' ' + (changes.changed === null
									? Joomla.Text._('COM_LOCALISE_SOURCE_FILE_NO_EXPORT')
									: Joomla.Text._('COM_LOCALISE_SOURCE_FILE_CONTENT_CHANGES').replace('%s', changes.changed));
							}

							jQuery('#localise-extension-file-status').text(status);
						}
					}
				}

				if (reply.data.success_message)
				{
					jQuery('#flash-message-success').empty().show().html(reply.data.success_message).delay(2000).fadeOut(300);
				}
				else if (reply.data.error_message)
				{
					jQuery('#flash-message-danger').empty().show().html(reply.data.error_message).delay(2000).fadeOut(300);
				}
			}
			else
			{
				if (reply.data.error_message)
				{
					jQuery('#flash-message-danger').empty().show().html(reply.data.error_message).delay(2000).fadeOut(300);
				}
			}
		}
	})
	.fail(function(jqXHR, textStatus, errorThrown)
	{
		if (request !== localiseFileRequest)
		{
			return;
		}
		//console.log('ajax call failed\\n' + textStatus + '\\n'+ errorThrown);

		// Reaching this point means that the Ajax request itself was not successful
		// So JsonResponse was never called

		// Here we can handle an alert message type 'System message', creating it. Sample:
		//var messages = {
		//					'message': ['Sample message one', 'Sample message two'],
		//					'error'  : ['Sample error one', 'Sample error two']
		//};

		var messages = {
						'error': ['" . Text::_('COM_LOCALISE_TASK_THROWN_ERROR') . "']
		};

		Joomla.renderMessages(messages);

		// Here we can handle an alert message type 'flash'.
		jQuery('#flash-message-danger').empty().show().html('" . Text::_('COM_LOCALISE_TASK_THROWN_ERROR_FLASH') . "').delay(2000).fadeOut(300);
	})
	.always(function() {
		if (request === localiseFileRequest)
		{
			checkButton.prop('disabled', !source || !languagetag);
		}
	});
}

jQuery(document).ready(function() {
	jQuery('#jform_language, #localise-installed-extension').change(function(){
		var source = jQuery('#localise-installed-extension').val();
		jQuery('#jform_checkedsourcefiles').val('');
		var keepSelection = this.id === 'localise-installed-extension' && Boolean(jQuery('#jform_id').val());
		updateTranslationsList(keepSelection && Boolean(source));
	});
	jQuery('#localise-check-file-changes').click(function() {
		updateTranslationsList(true);
	});
});
");
?>
<?php
	echo '<div class="text-center"><div id="flash-message-danger" class="alert alert-danger flash-message" style="display: none;"></div></div>';
	echo '<div class="text-center"><div id="flash-message-notice" class="alert alert-notice flash-message" style="display: none;"></div></div>';
	echo '<div class="text-center"><div id="flash-message-success" class="alert alert-success flash-message" style="display: none;"></div></div>';
?>
<form action="<?php echo Route::_('index.php?option=com_localise&view=packagefile&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="localise-package-form" class="form-validate">
	<div class="row-fluid">
		<!-- Begin Localise Package -->
		<div class="col-md-12">
				<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => $this->ftp ? 'ftp' : 'default')); ?>
					<?php if ($this->ftp) : ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'ftp', Text::_($ftpSets['ftp']->label, true)); ?>
						<?php if (!empty($ftpSets['ftp']->description)):?>
								<p class="tip"><?php echo Text::_($ftpSets['ftp']->description); ?></p>
						<?php endif;?>
						<?php if ($this->ftp instanceof Exception): ?>
								<p class="error"><?php echo Text::_($this->ftp->message); ?></p>
						<?php endif; ?>
						<?php foreach($this->formftp->getFieldset('ftp',false) as $field) : ?>
							<?php echo $field->renderField(); ?>
						<?php endforeach; ?>
					<?php echo HTMLHelper::_('uitab.endTab');; ?>
					<?php endif; ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'default', Text::_($fieldSets['default']->label, true)); ?>
						<div class="row">
						<div class="col-lg-12 col-xl-6">
							<fieldset id="fieldset-default" class="options-form">
								<legend><?php echo Text::_('COM_LOCALISE_FIELDSET_PACKAGE_DETAIL'); ?></legend>
								<div class="mb-3">
									<label for="localise-installed-extension"><?php echo Text::_('COM_LOCALISE_INSTALLED_EXTENSION'); ?></label>
									<select class="form-select" id="localise-installed-extension" name="jform[installedsource]" aria-describedby="localise-installed-extension-help">
										<option value=""><?php echo Text::_('COM_LOCALISE_INSTALLED_EXTENSION_SELECT'); ?></option>
										<?php if ($storedSource !== '' && !InstalledExtensionLanguages::getSourceId($storedSource)) : ?>
											<option selected value="<?php echo $this->escape($storedSource); ?>"><?php echo Text::_('COM_LOCALISE_SOURCE_UNAVAILABLE'); ?></option>
										<?php endif; ?>
										<?php foreach ($installedSources as $extension) : ?>
											<?php $key = InstalledExtensionLanguages::getSourceKey($extension); ?>
											<option value="<?php echo $this->escape($key); ?>" <?php echo $key === $storedSource ? 'selected' : ''; ?>><?php echo $this->escape(Text::_($extension->name) . ' (' . $extension->element . ')'); ?></option>
										<?php endforeach; ?>
									</select>
									<div id="localise-installed-extension-help" class="form-text"><?php echo Text::_('COM_LOCALISE_INSTALLED_EXTENSION_HELP'); ?></div>
								</div>
								<?php echo $this->form->renderField('language'); ?>
								<div class="mb-3">
									<label for="localise-extension-archive-filename"><?php echo Text::_('COM_LOCALISE_CORE_ARCHIVE_FILENAME'); ?></label>
									<input type="text" class="form-control" id="localise-extension-archive-filename" name="jform[archive_filename]" aria-describedby="localise-extension-archive-description">
									<div class="form-text" id="localise-extension-archive-description"><?php echo Text::_('COM_LOCALISE_EXTENSION_ARCHIVE_DESCRIPTION'); ?></div>
									<label class="d-block mt-2">
										<input type="checkbox" id="localise-extension-standard-archive" name="jform[standard_archive]" value="1">
										<?php echo Text::_('COM_LOCALISE_EXTENSION_STANDARD_ARCHIVE'); ?>
									</label>
									<output class="font-monospace mt-2" id="localise-extension-archive-preview" for="localise-extension-standard-archive jform_language jform_version jform_packversion" data-template="<?php echo PackageModel::CORE_ARCHIVE_NAME; ?>" aria-live="polite" hidden></output>
								</div>
								<?php foreach($this->form->getFieldset('default') as $field) : ?>
									<?php if (!in_array($field->fieldname, ['language', 'installedsource'], true)) echo $field->renderField(); ?>
								<?php endforeach; ?>
							</fieldset>
						</div>
						<div class="col-lg-12 col-xl-6">
							<fieldset id="fieldset-translations" class="options-form">
								<legend><?php echo Text::_($fieldSets['translations']->label); ?></legend>
								<button id="localise-check-file-changes" class="btn btn-outline-secondary mb-2" type="button"
									<?php echo $storedSource !== '' && $this->form->getValue('language') ? '' : 'disabled'; ?>>
									<?php echo Text::_('COM_LOCALISE_CHECK_FILE_CHANGES'); ?>
								</button>
								<button id="localise-preview-export" class="btn btn-outline-secondary mb-2" type="button"
									aria-controls="localise-export-preview" aria-expanded="false">
									<?php echo Text::_('COM_LOCALISE_PREVIEW_EXPORT'); ?>
								</button>
								<section id="localise-export-preview" aria-live="polite" hidden></section>
								<section id="localise-review-files" hidden>
									<h3 class="h5"><?php echo Text::_('COM_LOCALISE_REVIEW_FILE_CHANGES'); ?></h3>
									<p data-review-empty hidden><?php echo Text::_('COM_LOCALISE_EXPORT_STATUS_UNCHANGED'); ?></p>
									<div data-review-list></div>
									<button type="button" class="btn btn-outline-secondary mb-2" data-review-exit>
										<?php echo Text::_('COM_LOCALISE_REVIEW_ALL_FILES'); ?>
									</button>
								</section>
								<p id="localise-extension-file-status" role="status"></p>
								<?php if (!empty($fieldSets['translations']->description)):?>
										<p><?php echo Text::_($fieldSets['translations']->description); ?></p>
								<?php endif;?>
								<?php foreach($this->form->getFieldset('translations') as $field) : ?>
									<?php echo $field->renderField(); ?>
								<?php endforeach; ?>
							</fieldset>
						</div>
						</div>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'permissions', Text::_($fieldSets['permissions']->label, true)); ?>
						<fieldset id="fieldset-rules" class="options-form">
							<legend><?php echo Text::_($fieldSets['permissions']->label, true); ?></legend>
							<div>
								<?php echo $this->form->getInput('rules'); ?>
							</div>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<input type="hidden" name="task" value="" />
					<?php echo HTMLHelper::_('form.token'); ?>
				<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
		</div>
		<!-- End Localise Package -->
	</div>
</form>

<script>
(() => {
	const form = document.getElementById('localise-package-form');
	const button = document.getElementById('localise-preview-export');
	const preview = document.getElementById('localise-export-preview');
	const labels = <?php echo json_encode([
		'site' => Text::_('JSITE'),
		'administrator' => Text::_('JADMINISTRATOR'),
		'changed' => Text::_('COM_LOCALISE_PREVIEW_CHANGED'),
		'omitted' => Text::_('COM_LOCALISE_PREVIEW_OMITTED'),
		'missing' => Text::_('COM_LOCALISE_PREVIEW_MISSING'),
		'error' => Text::_('COM_LOCALISE_PREVIEW_ERROR'),
		'loading' => Text::_('COM_LOCALISE_PREVIEW_LOADING'),
		'noBaseline' => Text::_('COM_LOCALISE_PREVIEW_NO_BASELINE'),
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	let request = 0;

	function hidePreview() {
		request++;
		preview.hidden = true;
		preview.replaceChildren();
		button.setAttribute('aria-expanded', 'false');
	}

	function addList(title, entries) {
		const heading = document.createElement('h3');
		heading.className = 'h6';
		heading.textContent = title + ' (' + entries.length + ')';
		const list = document.createElement('ul');
		for (const entry of entries) {
			const item = document.createElement('li');
			item.textContent = entry;
			list.append(item);
		}
		preview.append(heading, list);
	}

	form.addEventListener('change', hidePreview);
	document.getElementById('localise-check-file-changes').addEventListener('click', hidePreview);
	button.addEventListener('click', async () => {
		if (!preview.hidden) {
			hidePreview();
			return;
		}
		const current = ++request;
		const data = new FormData(form);
		data.set('task', 'packagefile.previewexport');
		const url = new URL(form.action);
		url.searchParams.set('format', 'raw');
		preview.hidden = false;
		button.setAttribute('aria-expanded', 'true');
		preview.textContent = labels.loading;

		try {
			const response = await fetch(url, {method: 'POST', body: data});
			const reply = await response.json();
			if (current !== request) return;
			if (!response.ok || !reply.success) throw new Error(reply.message || labels.error);

			const {files, missing, hasBaseline} = reply.data;
			preview.replaceChildren();
			if (!hasBaseline) {
				const note = document.createElement('p');
				note.textContent = labels.noBaseline;
				preview.append(note);
			}
			if (missing.length) {
				addList(labels.missing, missing.map(file => labels[file.client] + ' ' + file.name));
			}
			for (const client of ['site', 'administrator']) {
				addList(labels[client], (files[client] || []).map(file =>
					file.archive + ' → ' + file.target + (file.changed ? ' (' + labels.changed + ')' : '')
				));
			}
			const excluded = Array.from(form.querySelectorAll('#jform_translations option'))
				.filter(option => !option.selected && /^(site|administrator)_/.test(option.value))
				.map(option => option.parentElement.label + ' ' + option.text);
			if (excluded.length) addList(labels.omitted, excluded);
		} catch (error) {
			if (current === request) preview.textContent = error.message || labels.error;
		}
	});
	if (window.location.hash === '#localise-preview-export') button.click();

	const review = document.getElementById('localise-review-files');
	const list = review.querySelector('[data-review-list]');
	const select = form.querySelector('#jform_translations');
	const field = select.closest('.control-group') || select;

	jQuery(review).on('localise:reviewReset', () => {
		review.hidden = true;
		field.hidden = false;
		list.replaceChildren();
	}).on('localise:reviewReady', (_event, keys) => {
		const changed = new Set(keys);
		for (const group of select.querySelectorAll('optgroup')) {
			const options = Array.from(group.querySelectorAll('option')).filter(option => changed.has(option.value));
			if (!options.length) continue;

			const section = document.createElement('fieldset');
			section.className = 'mb-3';
			const heading = document.createElement('legend');
			heading.className = 'h6';
			heading.textContent = group.label;
			section.append(heading);

			for (const option of options) {
				const row = document.createElement('label');
				row.className = 'form-check';
				const checkbox = document.createElement('input');
				checkbox.type = 'checkbox';
				checkbox.className = 'form-check-input';
				checkbox.checked = option.selected;
				checkbox.addEventListener('change', () => {
					option.selected = checkbox.checked;
					select.dispatchEvent(new Event('change', {bubbles: true}));
				});
				row.append(checkbox, option.text);
				section.append(row);
			}
			list.append(section);
		}
		review.querySelector('[data-review-empty]').hidden = Boolean(list.childElementCount);
		field.hidden = true;
		review.hidden = false;
		review.scrollIntoView({block: 'nearest'});
	});
	review.querySelector('[data-review-exit]').addEventListener('click', () => {
		history.replaceState(null, '', window.location.pathname + window.location.search);
		jQuery(review).trigger('localise:reviewReset');
		document.getElementById('localise-check-file-changes').focus();
	});
	if (window.location.hash === '#localise-review-files') {
		jQuery(() => document.getElementById('localise-check-file-changes').click());
	}
})();
</script>
