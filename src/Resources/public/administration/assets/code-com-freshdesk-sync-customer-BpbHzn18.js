const a=`{% block sw_customer_detail_base %} <div class="sw-customer-detail-base"> {% block sw_customer_detail_base_info_holder %} <div class="sw-customer-detail-base__info-holder"> {% block sw_customer_detail_base_info_card %} <sw-customer-card :title="$t('sw-customer.detailBase.labelAccountCard')" :customer="customer" :edit-mode="customerEditMode" :is-loading="isLoading" > {% block sw_customer_detail_base_info_metadata %} <sw-customer-base-info :customer="customer" :is-loading="isLoading" :customer-edit-mode="customerEditMode" /> {% endblock %} </sw-customer-card> {% endblock %} {% block sw_customer_detail_base_freshdesk_sync_card %} <mt-card title="Freshdesk" position-identifier="sw-customer-detail-base-freshdesk-sync" :is-loading="isLoading || freshdeskSyncInProgress" > <mt-switch :model-value="freshdeskOptinValue" label="Opt-in for digital communication" :disabled="freshdeskOptinSaving || freshdeskSyncInProgress" @update:model-value="onFreshdeskOptinChange" /> <sw-button-process variant="primary" :isLoading="freshdeskSyncInProgress" :processSuccess="freshdeskSyncSuccessful" :disabled="freshdeskSyncInProgress" @process-finish="onFreshdeskSyncFinished" @click="onFreshdeskSyncCustomer" > Sync to Freshdesk </sw-button-process> </mt-card> {% endblock %} {% block sw_customer_detail_base_default_addresses_card %} <mt-card v-if="customer.defaultShippingAddress || customer.defaultBillingAddress" :title="$t('sw-customer.detailBase.labelAddressesCard')" position-identifier="sw-customer-detail-base-default-addresses" class="sw-customer-detail-base__default-addresses" :is-loading="customer.isLoading" > {% block sw_customer_detail_base_default_addresses %} <template #grid> <sw-customer-default-addresses :customer-edit-mode="customerEditMode" :customer="customer" /> </template> {% endblock %} </mt-card> {% endblock %} {% block sw_customer_detail_custom_field_sets %} <mt-card v-if="!!customerCustomFieldSets && customerCustomFieldSets.length > 0" position-identifier="sw-customer-detail-base-custom-field-sets" :title="$t('sw-settings-custom-field.general.mainMenuItemGeneral')" :is-loading="customer.isLoading" > <sw-custom-field-set-renderer :entity="customer" :disabled="!customerEditMode" :sets="customerCustomFieldSets" /> </mt-card> {% endblock %} </div> {% endblock %} </div> {% endblock %}`,{Component:n,Mixin:l}=Shopware;n.override("sw-customer-detail-base",{template:a,inject:["repositoryFactory"],mixins:[l.getByName("notification")],data(){return{freshdeskSyncInProgress:!1,freshdeskSyncSuccessful:!1,freshdeskOptinSaving:!1,freshdeskOptinValue:!1}},watch:{customer:{immediate:!0,handler(){var e,s;this.freshdeskOptinValue=!!((s=(e=this.customer)==null?void 0:e.customFields)!=null&&s.freshdesk_sync_contact_consent)}}},computed:{customerRepository(){return this.repositoryFactory.create("customer")}},methods:{freshdeskHeaders(){return{Authorization:`Bearer ${Shopware.Service("loginService").getToken()}`,"Content-Type":"application/json"}},async onFreshdeskOptinChange(e){var s,i,o,t,r,d;if((s=this.customer)!=null&&s.id){this.freshdeskOptinValue=e,this.freshdeskOptinSaving=!0;try{const c=await Shopware.Application.getContainer("init").httpClient.post(`_action/codecom-freshdesk-sync-customer/${this.customer.id}/optin`,{optin:e},{headers:this.freshdeskHeaders()});this.customer.customFields={...this.customer.customFields||{},freshdesk_sync_contact_consent:e},this.createNotificationSuccess({title:"Freshdesk Opt-in",message:((i=c.data)==null?void 0:i.message)||"Customer Opt-in saved."})}catch(c){this.freshdeskOptinValue=!!((t=(o=this.customer)==null?void 0:o.customFields)!=null&&t.freshdesk_sync_contact_consent),this.createNotificationError({title:"Freshdesk Opt-in failed",message:((d=(r=c.response)==null?void 0:r.data)==null?void 0:d.message)||c.message||"Customer Opt-in could not be saved."})}finally{this.freshdeskOptinSaving=!1}}},async onFreshdeskSyncCustomer(){var e,s,i,o;if((e=this.customer)!=null&&e.id){this.freshdeskSyncInProgress=!0;try{const t=await Shopware.Application.getContainer("init").httpClient.post(`_action/codecom-freshdesk-sync-customer/${this.customer.id}`,{optin:this.freshdeskOptinValue},{headers:this.freshdeskHeaders()});this.customer.customFields={...this.customer.customFields||{},freshdesk_sync_contact_consent:this.freshdeskOptinValue},await this.customerRepository.get(this.customer.id,Shopware.Context.api).then(r=>{this.customer.customFields=r.customFields}),this.createNotificationSuccess({title:"Freshdesk sync",message:((s=t.data)==null?void 0:s.message)||"Customer synced to Freshdesk."}),this.freshdeskSyncSuccessful=!0}catch(t){this.createNotificationError({title:"Freshdesk sync failed",message:((o=(i=t.response)==null?void 0:i.data)==null?void 0:o.message)||t.message||"Customer could not be synced."})}finally{this.freshdeskSyncInProgress=!1}}},onFreshdeskSyncFinished(){this.freshdeskSyncSuccessful=!1}}});
//# sourceMappingURL=code-com-freshdesk-sync-customer-BpbHzn18.js.map
Shopware.Component.register('codecom-freshdesk-sync-reset-button', {
    template: `<div class="codecom-freshdesk-sync-reset-button" style="margin-bottom: 20px;">
        <sw-button-process
            variant="ghost-danger"
            :isLoading="isLoading"
            :processSuccess="isSuccess"
            @process-finish="onProcessFinish"
            @click="onResetStatus"
        >
            Reset Customer Sync Status &amp; Logs
        </sw-button-process>
    </div>`,
    inject: ['repositoryFactory'],
    mixins: [Shopware.Mixin.getByName('notification')],
    data() {
        return { isLoading: false, isSuccess: false };
    },
    methods: {
        freshdeskHeaders() {
            return {
                Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`,
                'Content-Type': 'application/json',
            };
        },
        async onResetStatus() {
            this.isLoading = true;
            try {
                const response = await Shopware.Application.getContainer('init').httpClient.post(
                    '_action/codecom-freshdesk-sync-customer-reset-status',
                    {},
                    { headers: this.freshdeskHeaders() }
                );

                this.createNotificationSuccess({
                    title: 'Freshdesk Reset',
                    message: response.data?.message || 'Customer sync status and logs reset successfully.',
                });
                this.isSuccess = true;
            } catch (error) {
                this.createNotificationError({
                    title: 'Reset failed',
                    message: error.response?.data?.message || error.message || 'Could not reset sync status.',
                });
            } finally {
                this.isLoading = false;
            }
        },
        onProcessFinish() {
            this.isSuccess = false;
        },
    },
});
