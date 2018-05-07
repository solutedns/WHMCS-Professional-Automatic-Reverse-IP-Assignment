<?php

/**
 *                      *** SoluteDNS ARIA for WHMCS ***
 *
 * @file        
 * @package     solutedns_aria
 *
 * Copyright (c) 2018 NetDistrict
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @license     SoluteDNS - End User License Agreement, http://www.solutedns.com/eula/
 * @author      NetDistrict <info@netdistrict.net>
 * @copyright   NetDistrict
 * @link        https://www.solutedns.com
 * */
if (!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Module Configuration.
 *
 * @return array
 */
function solutedns_aria_config() {
	return array(
		'name' => 'SoluteDNS - ARIA',
		'description' => 'Automatic Reverse IP Assignment for SoluteDNS for WHMCS.',
		'author' => 'NetDistrict',
		'language' => 'english',
		'version' => '1.18.001'
	);
}

/**
 * Activate.
 *
 * Called upon activation of the module for the first time.
 *
 * @return array Optional success/failure message
 */
function solutedns_aria_activate() {

	try {

		require('hooks.php');

		$i = 0;

		foreach (Capsule::table('tblhosting')->where('domainstatus', 'Active')->get() as $tbldata) {

			$vars['userid'] = $tbldata->userid;
			$vars['serviceid'] = $tbldata->id;

			SDNS_ARIA_update($vars);

			unset($vars);
			$i++;
		}

		return array(
			'status' => 'success', // Supported values here include: success, error or info
			'description' => "SoluteDNS for WHMCS - ARIA has been activated successfull and $i products have been processed.",
		);
	} catch (Exception $e) {

		$pdo->rollBack();

		return array(
			'status' => 'error', // Supported values here include: success, error or info
			'description' => 'An database error occured during activation: ' . $e->getMessage(),
		);
	}
}

/**
 * Deactivate.
 *
 * Called upon deactivation of the module.
 *
 * @return array Optional success/failure message
 */
function solutedns_aria_deactivate() {

	return array(
		'status' => 'success', // Supported values here include: success, error or info
		'description' => 'SoluteDNS for WHMCS - ARIA has been deactivated successfully.',
	);
}
