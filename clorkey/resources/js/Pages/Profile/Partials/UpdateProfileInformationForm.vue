<script setup>
import { ref, computed } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { Camera, Trash2 } from 'lucide-vue-next';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const fileInput = ref(null);
const avatarPreview = ref(null);

const form = useForm({
    name: user.name,
    email: user.email,
    avatar: null,
    remove_avatar: false,
});

const previewUser = computed(() => {
    if (avatarPreview.value) {
        return { ...user, avatar_url: avatarPreview.value }
    }
    if (form.remove_avatar) {
        return { ...user, avatar_url: null }
    }
    return user
});

function selectAvatar() {
    fileInput.value.click();
}

function onAvatarSelected(e) {
    const file = e.target.files[0];
    if (!file) return;

    form.avatar = file;
    form.remove_avatar = false;

    const reader = new FileReader();
    reader.onload = (ev) => {
        avatarPreview.value = ev.target.result;
    };
    reader.readAsDataURL(file);
}

function removeAvatar() {
    form.avatar = null;
    form.remove_avatar = true;
    avatarPreview.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function submit() {
    router.post(route('profile.update'), {
        _method: 'post',
        name: form.name,
        email: form.email,
        avatar: form.avatar,
        remove_avatar: form.remove_avatar ? 1 : 0,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.avatar = null;
            avatarPreview.value = null;
            form.remove_avatar = false;
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: (errors) => {
            form.errors = errors;
        },
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Informações do Perfil
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Atualize as informações do seu perfil e endereço de e-mail.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <!-- Avatar Section -->
            <div class="flex items-center gap-6">
                <UserAvatar :user="previewUser" size="xl" />
                <div class="flex flex-col gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="onAvatarSelected"
                    />
                    <button
                        type="button"
                        @click="selectAvatar"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                    >
                        <Camera class="h-4 w-4" />
                        Alterar Foto
                    </button>
                    <button
                        v-if="user.avatar_url || avatarPreview"
                        type="button"
                        @click="removeAvatar"
                        class="inline-flex items-center gap-2 rounded-md border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 transition-colors"
                    >
                        <Trash2 class="h-4 w-4" />
                        Remover Foto
                    </button>
                    <p class="text-xs text-gray-500">JPG, PNG ou WebP. Máx. 2MB.</p>
                </div>
                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

            <div>
                <InputLabel for="name" value="Nome" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Seu endereço de e-mail não foi verificado.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Clique aqui para reenviar o e-mail de verificação.
                    </Link>
                </p>
                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Um novo link de verificação foi enviado para o seu e-mail.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">
                        Salvo.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
