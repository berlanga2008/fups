<?php

/* 
 * FUPS: Forum user-post scraper. An extensible PHP framework for scraping and
 * outputting the posts of a specified user from a specified forum/board
 * running supported forum software. Can be run as either a web app or a
 * commandline script.
 *
 * Copyright (C) 2013-2014 Laird Shaw.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/* File       : common.php.
 * Description: Contains defines and functions shared between FUPS scripts.
 */

// Must come after the above define because that define is used in settings.php.
require_once __DIR__.'/settings.php';

define('FUPS_DONE_STR'               , 'DONE'     );
define('FUPS_FAILED_STR'             , 'EXITING'  );
define('FUPS_CANCELLED_STR'          , 'CANCELLED');
define('FUPS_MAX_TOKEN_ATTEMPTS'     ,          10);
define('FUPS_FALLBACK_FUPS_CHAIN_DURATION',   1200);

function format_html($html) {
	$flags = defined('ENT_SUBSTITUTE') ? ENT_SUBSTITUTE : (ENT_COMPAT | ENT_HTML401);
	return str_replace("\n", "<br />\n", htmlspecialchars($html, $flags));
}

function make_cancellation_filename($token) {
	return FUPS_DATADIR.$token.'.cancel.txt';
}

function make_cookie_filename($token_or_settings_filename) {
	return ($token_or_settings_filename[0] == '/' ? '' : FUPS_DATADIR).$token_or_settings_filename.'.cookies.txt';
}

function make_errs_filename($token) {
	return FUPS_DATADIR.$token.'.errs.txt';
}

function make_errs_admin_filename($token) {
	return FUPS_DATADIR.$token.'.errs.admin.txt';
}

function make_output_filename($token, $for_web = false) {
	return ($for_web ? FUPS_OUTPUTDIR_WEB : FUPS_OUTPUTDIR).$token.'.html';
}

function make_php_exec_cmd($params) {
	$args = '';
	$redirect = ' 1>/dev/null';
	if (isset($params['token'])) {
		if ($args) $args .= ' ';
		$args .= '-t '.escapeshellarg($params['token']);
		$errs_fname = make_errs_filename($params['token']);
		$redirect = ' 1>>'.$errs_fname.' 2>>'.$errs_fname;
	}
	if (isset($params['settings_filename'])) {
		if ($args) $args .= ' ';
		$args .= '-i '.escapeshellarg($params['settings_filename']);
	}
	if (isset($params['output_filename'])) {
		if ($args) $args .= ' ';
		$args .= '-o '.escapeshellarg($params['output_filename']);
	}
	if (isset($params['chained']) && $params['chained'] == true) {
		if ($args) $args .= ' ';
		$args .= '-c';
	}

	return FUPS_CMDLINE_PHP_PATH.' -d max_execution_time=0 fups.php '.$args.$redirect.' &';
}

function make_serialize_filename($token_or_settings_filename) {
	return ($token_or_settings_filename[0] == '/' ? '' : FUPS_DATADIR).$token_or_settings_filename.'.serialize.txt';
}

function make_settings_filename($token) {
	return FUPS_DATADIR.$token.'.settings.txt';
}

function make_status_filename($token) {
	return FUPS_DATADIR.$token.'.status.txt';
}

function validate_token($token, &$err) {
	$err = '';
	if (strlen($token) <> 32) {
		$err = 'A fatal error occurred: token is malformed (length).';
	} else {
		$malformed_char = false;
		for ($i = 0; $i < strlen($token); $i++) {
			$ch = $token[$i];
			if (!($ch >= '0' && $ch <= '9') && !($ch >= 'a' && $ch <= 'z')) {
				$malformed_char = true;
				break;
			}
		}
		if ($malformed_char) {
			$err = 'A fatal error occurred: token is malformed (character).';
		}
	}

	return $err == '';
}

function get_failed_done_cancelled($status, &$done, &$cancelled, &$failed) {
	$failed = (substr($status, -strlen(FUPS_FAILED_STR)) == FUPS_FAILED_STR);
	$done = (substr($status, -strlen(FUPS_DONE_STR)) == FUPS_DONE_STR);
	$cancelled = (substr($status, -strlen(FUPS_CANCELLED_STR)) == FUPS_CANCELLED_STR);
}

function show_delete($token, $had_success = false) {
?>
			<p>For your privacy, you might wish to delete from this web server all session and output files associated with this request, especially if you have supplied a login username and password (files that store your username and password details are not publicly visible, but it is wise to delete them anyway).<?php echo ROUTINE_DELETION_POLICY; ?></p>
<?php	if ($had_success) { ?>
			<p>Be sure to do this only <strong>after</strong> you have clicked the above "View result" link, and saved the contents at that page, because they will no longer be accessible after clicking the following link.</p>
<?php	} ?>
			<p><a href="delete-files.php?token=<?php echo htmlspecialchars(urlencode($token)); ?>">Delete all files</a> associated with your scrape from my web server - this includes your settings, including your password if you entered one.</p>
<?php
}

function output_update_html($token, $status, $done, $cancelled, $failed, $err, $errs, $errs_admin = false, $ajax = false) {
	if ($err) {
?>
			<div class="fups_error"><?php echo format_html($err); ?></div>
<?php
		return;
	}
?>
			<h3>Status</h3>

			<div id="fups_div_status">
<?php	echo htmlspecialchars($status); ?>
			</div>

<?php
	if ($done) {
		$output_filename = make_output_filename($token, true);
?>
			<p>Success! Your posts were retrieved and the output is ready: <a target="_blank" href="<?php echo $output_filename; ?>">View result</a> (opens in a new window)</p>

			<p>If you're wondering what to do next, here are some possible steps:</p>
			<ol>
				<li>Switch to the window/tab that opened up when you clicked "View result", and save the page, e.g. in Firefox click the "File" menu option and under that click "Save Page As". Select the directory/folder and filename you wish to save this output as (remember this location for the next step).</li>
				<li>Start up a word processor such as OpenOffice or Microsoft word. Open up in that word processor the HTML file that you saved in the previous step, e.g. click the "File" menu option and under that click "Open". You are now free to edit the file as you like. You can now (if you so desire) save the file in a friendlier format than HTML, a format such as your editor's default format, e.g. in OpenOffice, click the "File" menu option and then click "Save As" or "Export", and choose the format you desire.</li>
			</ol>
<?php
		show_delete($token, true);
	} else if ($cancelled) {
?>
			<p>Cancelled by your request.</p>
<?php
		show_delete($token, false);
	} else if ($failed) {
?>
			<p>The script appears to have exited due to an error; the error message is shown below. I have been notified of this error by email; if you would like me to get back to you if/when I have fixed the error, then please enter your email address into the following box and press the button to notify me of it.</p>

			<div>
				<form method="post" action="notify-email-address.php">
					<input type="hidden" name="token" value="<?php echo $token; ?>" />
					<label for="email_address.id">Your contact email address:</label><br />
					<input type="text" name="email_address" id="email_address.id" /><br />
					<label for="message.id">Any message you'd like to include (leaving this blank is fine):</label><br />
					<textarea rows="5" cols="80" name="message" id="message.id"></textarea><br />
					<input type="submit" value="Notify the FUPS maintainer" />
				</form>
			</div>

			<p>Alternatively, feel free to retry or to <a href="<?php echo FUPS_CONTACT_URL; ?>">contact me</a> manually about this error, quoting your run token of "<?php echo $token; ?>".</p>

<?php
		show_delete($token, false);
	} else {
		$same_status = (isset($_GET['last_status']) && $status == $_GET['last_status']);
?>
			<p>
				<a href="<?php echo 'run.php?token='.$token.($same_status ? '&amp;last_status='.htmlspecialchars(urlencode($status)) : '').($ajax ? '&amp;ajax=yes' : ''); ?>"><?php echo ($ajax ? 'Refresh page' : 'Check progress'); ?></a><?php if ($ajax): echo ' (it should not be necessary to click this link unless something goes wrong)'; endif; ?>.
<?php		if ($same_status) { ?>
				(It appears that progress has halted unexpectedly - the current status is the same as the previous status. It is likely that an error has caused the process to exit before finishing. We are sorry about this failure. In case you want to be sure that progress has indeed halted, you are welcome to click the preceding link, but otherwise, this page will no longer automatically refresh.)

<?php
			show_delete($token, false);
		} else { ?>
				<?php echo (!$ajax ? 'Your browser should automatically refresh this page every '.FUPS_META_REDIRECT_DELAY.' seconds or so to update progress, but if you don\'t want to wait, you\'re welcome to click the link. ' : ''); ?>If you have changed your mind about wanting to run this script through to the end, <strong>please</strong> click this <a href="cancel.php?token=<?php echo $token.($ajax ? '&amp;ajax=yes' : ''); ?>">cancel</a> link rather than just closing this page - clicking the cancel link will free up the resources (in particular a background process) associated with your task.
<?php		} ?>
			</p>
<?php
	}
	if ($errs) {
?>

			<h3>Errors</h3>

			<div class="fups_error">
<?php	echo format_html($errs); ?>
			</div>
<?php
		if ($errs_admin) {
			// The toggle_ext_errs() Javascript function below is defined in run.php
?>

			<p><a href="javascript:toggle_ext_errs();">Show/hide extended error messages</a> (these have been emailed to me as-is, with your token, "<?php echo htmlspecialchars($token); ?>", included in the email's subject)</p>

			<div id="id_ext_err" style="display: none;">

				<h3>Extended error messages</h3>

				<div class="fups_error"><?php echo format_html($errs_admin); ?></div>
			</div>
<?php		}
	}

	// Early return possible
}

?>
