import template from './sw-customer-detail-base.html.twig';

const { Component, Mixin } = Shopware;

Component.override('sw-customer-detail-base', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            freshdeskSyncInProgress: false,
            freshdeskSyncSuccessful: false,
            freshdeskOptinSaving: false,
            freshdeskOptinValue: false,
        };
    },

    watch: {
        customer: {
            immediate: true,
            handler() {
                this.freshdeskOptinValue = !!this.customer?.customFields?.freshdesk_sync_contact_consent;
            },
        },
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },
    },

    methods: {
        freshdeskHeaders() {
            return {
                Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`,
                'Content-Type': 'application/json',
            };
        },

        async onFreshdeskOptinChange(value) {
            if (!this.customer?.id) {
                return;
            }

            this.freshdeskOptinValue = value;
            this.freshdeskOptinSaving = true;

            try {
                const response = await Shopware.Application.getContainer('init').httpClient.post(
                    `_action/codecom-freshdesk-sync-customer/${this.customer.id}/optin`,
                    { optin: value },
                    { headers: this.freshdeskHeaders() },
                );

                this.customer.customFields = {
                    ...(this.customer.customFields || {}),
                    freshdesk_sync_contact_consent: value,
                };

                this.createNotificationSuccess({
                    title: 'Freshdesk Opt-in',
                    message: response.data?.message || 'Customer Opt-in saved.',
                });
            } catch (error) {
                this.freshdeskOptinValue = !!this.customer?.customFields?.freshdesk_sync_contact_consent;
                this.createNotificationError({
                    title: 'Freshdesk Opt-in failed',
                    message: error.response?.data?.message || error.message || 'Customer Opt-in could not be saved.',
                });
            } finally {
                this.freshdeskOptinSaving = false;
            }
        },

        async onFreshdeskSyncCustomer() {
            if (!this.customer?.id) {
                return;
            }

            this.freshdeskSyncInProgress = true;

            try {
                const response = await Shopware.Application.getContainer('init').httpClient.post(
                    `_action/codecom-freshdesk-sync-customer/${this.customer.id}`,
                    { optin: this.freshdeskOptinValue },
                    { headers: this.freshdeskHeaders() },
                );

                this.customer.customFields = {
                    ...(this.customer.customFields || {}),
                    freshdesk_sync_contact_consent: this.freshdeskOptinValue,
                };

                await this.customerRepository.get(this.customer.id, Shopware.Context.api).then((customer) => {
                    this.customer.customFields = customer.customFields;
                });

                this.createNotificationSuccess({
                    title: 'Freshdesk sync',
                    message: response.data?.message || 'Customer synced to Freshdesk.',
                });
                this.freshdeskSyncSuccessful = true;
            } catch (error) {
                this.createNotificationError({
                    title: 'Freshdesk sync failed',
                    message: error.response?.data?.message || error.message || 'Customer could not be synced.',
                });
            } finally {
                this.freshdeskSyncInProgress = false;
            }
        },

        onFreshdeskSyncFinished() {
            this.freshdeskSyncSuccessful = false;
        },
    },
});
