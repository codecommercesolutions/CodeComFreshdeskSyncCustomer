import template from './codecom-freshdesk-sync-reset-button.html.twig';

const { Component, Mixin } = Shopware;

Component.register('codecom-freshdesk-sync-reset-button', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSuccess: false,
        };
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
