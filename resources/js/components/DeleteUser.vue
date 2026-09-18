<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import PasswordInput from '@/components/PasswordInput.vue';

const passwordInput = useTemplateRef('passwordInput');
const open = ref(false);
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Delete account"
            description="Delete your account and all of its resources"
        />

        <UAlert
            color="error"
            variant="subtle"
            title="Warning"
            description="Please proceed with caution, this cannot be undone."
        >
            <template #actions>
                <UModal
                    v-model:open="open"
                    title="Are you sure you want to delete your account?"
                    description="Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account."
                >
                    <UButton color="error" data-test="delete-user-button">
                        Delete account
                    </UButton>

                    <template #body>
                        <Form
                            v-bind="ProfileController.destroy.form()"
                            reset-on-success
                            @error="() => passwordInput?.focus()"
                            :options="{ preserveScroll: true }"
                            class="space-y-6"
                            v-slot="{ errors, processing, reset, clearErrors }"
                        >
                            <UFormField
                                label="Password"
                                :error="errors.password"
                            >
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref="passwordInput"
                                    placeholder="Password"
                                />
                            </UFormField>

                            <div class="flex justify-end gap-2">
                                <UButton
                                    color="neutral"
                                    variant="outline"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                            open = false;
                                        }
                                    "
                                >
                                    Cancel
                                </UButton>
                                <UButton
                                    type="submit"
                                    color="error"
                                    :loading="processing"
                                    data-test="confirm-delete-user-button"
                                >
                                    Delete account
                                </UButton>
                            </div>
                        </Form>
                    </template>
                </UModal>
            </template>
        </UAlert>
    </div>
</template>
