<?php
/*
Copyright 2009-2022 CentEDI s.r.o.
*/
$CentediUtils = new CentediUtils();

?>
<script>
	// Globals
	var CEDI_BASE_URL = "<?php echo $CentediUtils->getUrl() ?>";
	var CEDI_AUTH_URL = "<?php echo $CentediUtils->getServiceUrl() ?>";
	var CEDI_PLUGIN_URL = "<?php echo plugin_dir_url(__FILE__) ?>";
</script>
<div id="status_container"></div>
<h2><?php echo __("Registration", 'centedi-cataddon') ?></h2>
<form method="post" style="width: 500px;" action="" novalidation="novalidation">
	<table class="form-table" id="cedi_registration_form">
		<tbody>
			<tr>
				<th scope="row"><label for="org"><?php echo __("Organization", 'centedi-cataddon') ?></label></th>
				<td><input disabled name="org" type="text" id="org" value="<?php echo $CentediUtils->getOrganization(); ?>" class="code regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="email"><?php echo __("Email", 'centedi-cataddon') ?></label></th>
				<td><input disabled name="email" type="email" id="email" value="<?php echo $CentediUtils->getEmail(); ?>" class="code regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="country"><?php echo __("Country", 'centedi-cataddon') ?></label></th>
				<td><select disabled initvalue="<?php echo $CentediUtils->getCountry(); ?>" data-role="country-selector" data-code-mode="alpha2" name="country" id="country" class="regular-text code"></select></td>
			</tr>
			<tr>
				<th scope="row"><label for="url"><?php echo __("URL", 'centedi-cataddon') ?></label></th>
				<td><input disabled readonly name="url" type="url" id="url" value="<?php echo $CentediUtils->getUrl(); ?>" class="regular-text"></td>
			</tr>
		</tbody>
	</table>
	<div id="button_container">
		<?php echo $CentediUtils->getRegButtonHtml(); ?>
	</div>
</form>


<fieldset id="pass_twice_form_container" hidden>
	<form id="pass_twice_form" action="" method="post" style="position: relative;">
		<span>
			<h3 id="auth_msg"></h3>
		</span>
		<div class="centedi_reg" id="pfc">
			<label for="pass_first"><span><?php echo __('Password', 'centedi-cataddon') ?></span></label>
			<div class="control">
				<input name="pass_first" id="pass_first" title="<?php echo __('Password', 'centedi-cataddon') ?>" class="input-text" type="password" />
			</div>
			<span id="pwdstatus"></span>
		</div>
		</br>
		<div class="centedi_reg" id="psc">
			<label for="pass_sec"><span><?php echo __('Retype password', 'centedi-cataddon') ?></span></label>
			<div class="control">
				<input name="pass_sec" id="pass_sec" title="<?php echo __('Retype password', 'centedi-cataddon') ?>" class="input-text" type="password" />
			</div>
			<span id="pwdsecstatus"></span>
		</div>
		</br>
	</form>
</fieldset>