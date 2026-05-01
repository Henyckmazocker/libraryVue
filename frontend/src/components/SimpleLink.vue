<template>
  <div class="simple-link">
    <a :href="url" target="_blank" rel="noopener noreferrer" class="link-item">
      <i :class="getTypeIcon()"></i>
      <span class="link-text">{{ title || url }}</span>
      <i class="fas fa-external-link-alt link-external"></i>
    </a>
  </div>
</template>

<script>
export default {
  name: 'MediaEmbed',
  props: {
    url: {
      type: String,
      required: true
    },
    title: {
      type: String,
      default: ''
    }
  },
  methods: {
    getTypeIcon() {
      const url = this.url.toLowerCase()
      
      if (url.includes('youtube.com') || url.includes('youtu.be')) {
        return 'fab fa-youtube'
      } else if (url.includes('vimeo.com')) {
        return 'fab fa-vimeo'
      } else if (url.includes('reddit.com')) {
        return 'fab fa-reddit'
      } else if (url.includes('twitter.com') || url.includes('x.com')) {
        return 'fab fa-twitter'
      } else if (url.includes('instagram.com')) {
        return 'fab fa-instagram'
      } else if (url.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
        return 'fas fa-image'
      } else if (url.match(/\.(mp4|webm|ogg|avi|mov)$/i)) {
        return 'fas fa-video'
      } else if (url.match(/\.(mp3|wav|ogg|aac)$/i)) {
        return 'fas fa-music'
      } else if (url.match(/\.pdf$/i)) {
        return 'fas fa-file-pdf'
      } else {
        return 'fas fa-link'
      }
    }
  }
}
</script>

<style scoped lang="scss">
.simple-link {
  margin-bottom: 0.5rem;
}

.link-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  text-decoration: none;
  color: var(--color-text);
  transition: all 0.2s ease;
}

.link-item:hover {
  background: var(--color-background-mute);
  border-color: var(--color-border-hover);
  text-decoration: none;
  color: var(--color-text);
}

.link-item i:first-child {
  flex-shrink: 0;
  width: 1.25rem;
  text-align: center;
  color: var(--color-text-secondary);
}

.link-text {
  flex: 1;
  word-break: break-all;
  line-height: 1.4;
}

.link-external {
  flex-shrink: 0;
  font-size: 0.875rem;
  opacity: 0.6;
}

/* Iconos específicos de plataformas */
.fa-youtube {
  color: #ff0000;
}

.fa-vimeo {
  color: #1ab7ea;
}

.fa-reddit {
  color: #ff4500;
}

.fa-twitter {
  color: #1da1f2;
}

.fa-instagram {
  color: #e4405f;
}

.fa-image {
  color: #28a745;
}

.fa-video {
  color: #6f42c1;
}

.fa-music {
  color: #fd7e14;
}

.fa-file-pdf {
  color: #dc3545;
}
</style>
