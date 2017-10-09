<?php

/**
 *                      *** SoluteDNS ARIA for WHMCS ***
 *
 * @file        hooks.php
 * @package     solutedns_aria
 *
 * Copyright (c) 2017 NetDistrict
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
 * Admin Tab Field
 *
 * Add's an field to the admin's product details page to show the assigned state.
 */
add_hook('AdminClientServicesTabFields', 1, function($vars) {

	$tbldata = Capsule::table('tblhosting')->where('id', $vars['id'])->first();
	$ipList = array_filter(explode(PHP_EOL, $tbldata->assignedips));
	$userid = $tbldata->userid;

	$c = 0;
	$i = 0;

	foreach ($ipList as $ip) {

		$tbldata = Capsule::table('mod_solutedns_reverse')->where('ip', $ip)->where('client_id', $userid)->first();

		if ($tbldata) {
			$i++;
		}
		$c++;
	}

	if ($c == $i) {
		$class = 'text-success';
	} else {
		$class = 'text-danger';
	}
	
	return [
		'Reverse IP Assignment' => "Found <strong class=\"$class\">$c</strong> IP addresses of which <strong class=\"$class\">$i</strong> are assigned for reverse DNS Management.",
	];
});

/**
 * Update Assignment on Service update.
 */
add_hook('ServiceEdit', 1, function($vars) {
	SDNS_ARIA_update($vars);
});

/**
 * Update Assignment on Order Acceptance.
 */
add_hook('AcceptOrder', 1, function($vars) {

	foreach (Capsule::table('tblhosting')->where('orderid', $vars['orderid'])->get() as $tbldata) {

		$vars['userid'] = $tbldata->userid;
		$vars['serviceid'] = $tbldata->id;

		SDNS_ARIA_update($vars);

		unset($vars);
	}
});

/**
 * Reverse IP Assignment update function.
 */
function SDNS_ARIA_update($vars) {

	// Get assigned IP's
	$tbldata = Capsule::table('tblhosting')->where('id', $vars['serviceid'])->where('domainstatus', 'Active')->first();
	$ipList = explode(PHP_EOL, $tbldata->assignedips);

	// Handle each IP
	foreach ($ipList as $ip) {

		$arpa = false;

		// Validate and reverse IPv6
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {

			$addr = inet_pton($ip);
			$unpack = unpack('H*hex', $addr);
			$hex = $unpack['hex'];
			$arpa = implode('.', array_reverse(str_split($hex))) . '.ip6.arpa';
		}

		// Validate and reverse IPv4
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {

			$addr = explode(".", $ip);
			$arpa = $addr[3] . '.' . $addr[2] . '.' . $addr[1] . '.' . $addr[0] . '.in-addr.arpa';
		}

		// If reverse is set continue
		if ($arpa) {

			$time = time();
			$server_id = Capsule::table('tbladdonmodules')->where('module', 'solutedns_aria')->where('setting', 'serverid')->value('value');
			$tbldata = Capsule::table('mod_solutedns_reverse')->where('ip', $ip)->first();

			// Update existing assignment
			if ($tbldata) {

				Capsule::table('mod_solutedns_reverse')
					->where('ip', $ip)
					->update(
						[
							'client_id' => $vars['userid'],
							'server_id' => $server_id,
							'last_update' => $time,
						]
				);
			}
			// Create new assignment
			else {

				try {
					$db = remotedb::get($server_id);
					$stmt = $db->prepare("SELECT id,content FROM records WHERE name='$arpa' AND type='PTR';");
					$stmt->execute();
					$result = $stmt->fetch(PDO::FETCH_ASSOC);

					if ($result) {

						Capsule::table('mod_solutedns_reverse')->insert(
							[
								'client_id' => $vars['userid'],
								'server_id' => $server_id,
								'record_id' => $result['id'],
								'ip' => $ip,
								'hostname' => $result['content'],
								'last_update' => $time
							]
						);
					}
				} catch (Exception $e) {
					logActivity("ERROR: SoluteDNS - ARIA: " . $e->getMessage(), 0);
				}
			}
		}
	}
}
