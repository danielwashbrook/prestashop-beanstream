<div class="beanstream-wrapper">

<p class="beanstream-intro">{l s='Beanstream payment gateway.' mod='beanstream'}</p>


<form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
	<fieldset>
		<legend>{l s='Configure your Beanstream Accounts' mod='beanstream'}</legend>

		{* Determine which currencies are enabled on the store and supported by Authorize.net & list one credentials section per available currency *}
		{foreach from=$currencies item='currency'}
			{if (in_array($currency.iso_code, $available_currencies))}
				{assign var='configuration_id_name' value="BEAN_LOGIN_ID_"|cat:$currency.iso_code}
				{assign var='configuration_key_name' value="BEAN_KEY_"|cat:$currency.iso_code}
				<table>
					<tr>
						<td>
							<p>{l s='Credentials for' mod='beanstream'}<b> {$currency.iso_code}</b> {l s='currency' mod='beanstream'}</p>
							<label for="bean_login_id">{l s='Merchant ID' mod='beanstream'}:</label>
							<div class="margin-form" style="margin-bottom: 0px;"><input type="text" size="20" id="bean_login_id_{$currency.iso_code}" name="bean_login_id_{$currency.iso_code}" value="{${$configuration_id_name}}" /></div>
							<label for="bean_key">{l s='API Passcode' mod='beanstream'}:</label>
							<div class="margin-form" style="margin-bottom: 0px;"><input type="text" size="20" id="bean_key_{$currency.iso_code}" name="bean_key_{$currency.iso_code}" value="{${$configuration_key_name}}" /></div>
						</td>
					</tr>
				<table><br />
				<hr size="1" style="background: #BBB; margin: 0; height: 1px;" noshade /><br />
			{/if}
		{/foreach}

		<label for="beanstream_mode">{l s='Environment:' mod='beanstream'}</label>
		<div class="margin-form" id="beanstream_mode">
			<input type="radio" name="beanstream_mode" value="0" style="vertical-align: middle;" {if !$BEAN_SANDBOX && !$BEAN_TEST_MODE}checked="checked"{/if} />
			<span>{l s='Live mode' mod='beanstream'}</span><br/>
			<input type="radio" name="beanstream_mode" value="1" style="vertical-align: middle;" {if !$BEAN_SANDBOX && $BEAN_TEST_MODE}checked="checked"{/if} />
			<span>{l s='Test mode (in production server)' mod='beanstream'}</span><br/>
			<input type="radio" name="beanstream_mode" value="2" style="vertical-align: middle;" {if $BEAN_SANDBOX}checked="checked"{/if} />
			<span>{l s='Test mode' mod='beanstream'}</span><br/>
		</div>
		<label for="bean_cards">{l s='Cards* :' mod='beanstream'}</label>
		<div class="margin-form" id="bean_cards">
			<input type="checkbox" name="bean_card_visa" {if $BEAN_CARD_VISA}checked="checked"{/if} />
				<img src="{$module_dir}/cards/visa.gif" alt="visa" />
			<input type="checkbox" name="bean_card_mastercard" {if $BEAN_CARD_MASTERCARD}checked="checked"{/if} />
				<img src="{$module_dir}/cards/mastercard.gif" alt="visa" />
			<input type="checkbox" name="bean_card_discover" {if $BEAN_CARD_DISCOVER}checked="checked"{/if} />
				<img src="{$module_dir}/cards/discover.gif" alt="visa" />
			<input type="checkbox" name="bean_card_ax" {if $BEAN_CARD_AX}checked="checked"{/if} />
				<img src="{$module_dir}/cards/ax.gif" alt="visa" />
		</div>

		<label for="bean_hold_review_os">{l s='Order status:  "Hold for Review" ' mod='beanstream'}</label>
		<div class="margin-form">
			<select id="bean_hold_review_os" name="bean_hold_review_os">';
				// Hold for Review order state selection
				{foreach from=$order_states item='os'}
					<option value="{if $os.id_order_state|intval}" {((int)$os.id_order_state == $BEAN_HOLD_REVIEW_OS)} selected{/if}>
						{$os.name|stripslashes}
					</option>
				{/foreach}
			</select>
		</div>
		<br />
		<center>
			<input type="submit" name="submitModule" value="{l s='Update settings' mod='beanstream'}" class="button" />
		</center>
		<sub>{l s='* Subject to region' mod='beanstream'}</sub>
	</fieldset>
</form>
</div>
