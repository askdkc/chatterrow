<script lang="ts">
    let {
        class: className = '',
        ...rest
    }: {
        class?: string;
        [key: string]: unknown;
    } = $props();
</script>

<img
    src="/chatterrow-icon.png"
    alt=""
    aria-hidden="true"
    draggable="false"
    class={className}
    {...rest}
/>
