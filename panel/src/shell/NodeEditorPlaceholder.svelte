<script lang="ts">
	type Props = {
		collectionName: string;
	};

	let { collectionName }: Props = $props();
</script>

<div class="cms-node-placeholder" aria-hidden="true">
	<div class="sticky">
		<div class="bar">
			<div class="inner">
				<div class="trail">
					<span class="icon"></span>
					<span class="chevron"></span>
					<span class="collection">{collectionName}</span>
				</div>
				<div class="actions">
					<span class="button danger"></span>
					<span class="button secondary"></span>
					<span class="button primary"></span>
				</div>
			</div>
		</div>
		<div class="header-frame">
			<div class="headline">
				<span class="title"></span>
				<span class="status"></span>
			</div>
			<div class="tabs">
				<span class="tab active"></span>
				<span class="tab"></span>
			</div>
		</div>
	</div>
	<div class="body">
		<div class="card">
			<span class="line short"></span>
			<span class="line long"></span>
			<span class="line medium"></span>
		</div>
	</div>
</div>
<p class="cms-node-placeholder-status" role="status">Loading editor …</p>
