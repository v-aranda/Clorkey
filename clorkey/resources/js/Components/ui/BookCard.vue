<script setup>
import { computed } from 'vue';
import { Brain, FileText, Database, Code, Server, Globe } from 'lucide-vue-next';

const props = defineProps({
    color: {
        type: String,
        required: true,
    },
    textColor: {
        type: String,
        default: 'text-white',
    },
    icon: {
        type: [String, Object],
        default: null,
    },
    image: {
        type: String,
        default: null,
    },
});

const iconComponent = computed(() => {
    if (!props.icon) return null;
    const icons = { Brain, FileText, Database, Code, Server, Globe };
    return typeof props.icon === 'string' ? icons[props.icon] || FileText : props.icon;
});
</script>

<template>
    <div class="book-container group cursor-pointer">
        <div class="book">
            <!-- Front Cover -->
            <div class="cover" :class="[color, textColor]">
                <!-- Spine Gradient -->
                <div class="spine-gradient"></div>

                <!-- Content -->
                <div class="content">
                    <div class="icon-wrapper">
                        <img v-if="image" :src="image" alt="Book Cover"
                            class="h-full w-full object-contain drop-shadow-md" />
                        <component v-else-if="iconComponent" :is="iconComponent" class="h-12 w-12 drop-shadow-md" />
                    </div>

                    <h3 class="title">
                        <slot />
                    </h3>
                </div>

                <!-- Folding Crease -->
                <div class="crease"></div>
            </div>

            <!-- Pages/Inside -->
            <div class="pages">
                <div class="page-lines"></div>
            </div>

            <!-- Back Cover -->
            <div class="back-cover" :class="[color]"></div>
        </div>
    </div>
</template>

<style scoped>
/* Container & Perspective */
.book-container {
    perspective: 1000px;
    width: 12rem;
    /* w-48 */
    height: 16rem;
    /* h-64 */
    position: relative;
}

/* The Book Object */
.book {
    position: relative;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
    transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
    transform-origin: center left;
}

/* Hover State - Rotate the whole book slightly */
.book-container:hover .book {
    transform: rotateY(10deg) translateX(10px);
}

/* Common Absolute Positioning */
.cover,
.back-cover,
.pages {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 0 6px 6px 0;
    /* rounded-r-md */
    transform-origin: left;
    transform-style: preserve-3d;
}

/* FRONT COVER */
.cover {
    z-index: 20;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.15);
    border-left: 4px solid rgba(0, 0, 0, 0.1);
    /* Spine visual */
    transition: all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
}

/* OPENING EFFECT */
.book-container:hover .cover {
    transform: rotateY(-25deg);
    box-shadow: 10px 10px 30px rgba(0, 0, 0, 0.3);
}

/* Internal Elements */
.spine-gradient {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 16px;
    background: linear-gradient(to right, rgba(0, 0, 0, 0.2), transparent);
    mix-blend-mode: multiply;
    pointer-events: none;
}

.crease {
    position: absolute;
    left: 12px;
    top: 0;
    bottom: 0;
    width: 1px;
    background: rgba(255, 255, 255, 0.15);
}

.content {
    z-index: 30;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    text-align: center;
    transform: translateZ(20px);
    /* Pop out content slightly */
}

.icon-wrapper {
    height: 4rem;
    width: 4rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.title {
    font-weight: 700;
    font-size: 1.125rem;
    line-height: 1.25;
    user-select: none;
}

/* PAGES / INSIDE */
.pages {
    z-index: 10;
    width: 96%;
    height: 96%;
    top: 2%;
    /* Center vertically (2% top + 96% height + 2% bottom) */
    background-color: #f8fafc;
    /* gray-50 */
    border: 1px solid #e2e8f0;
    /* gray-200 */
    transform: translateZ(-2px);
    /* Slightly behind cover */
    box-shadow: inset -2px 0 5px rgba(0, 0, 0, 0.05);
    border-radius: 0 4px 4px 0;
    transition: all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
    /* Slightly smaller radius */
}

.book-container:hover .back-cover,
.book-container:hover .pages {
    transform: translateX(13%);
}

.page-lines {
    position: absolute;
    top: 10px;
    bottom: 10px;
    right: 5px;
    width: 2px;
    border-right: 1px solid #cbd5e1;
    border-left: 1px solid #cbd5e1;
}

/* BACK COVER */
.back-cover {
    z-index: 5;
    width: 100%;
    height: 100%;
    /* gray-300 */
    transform: translateZ(-10px);
    /* Thickness of book */
    box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
}
</style>
