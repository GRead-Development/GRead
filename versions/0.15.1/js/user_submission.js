document.addEventListener('DOMContentLoaded', function ()
{
	const modal = document.getElementById("ol-importer-modal");
	const btn = document.getElementById("ol-open-modal-btn");
	const span = document.getElementsByClassName("ol-modal-close")[0];

	if (btn)
	{
		btn.onclick = function()
		{
			modal.style.display = "block";
		}
	}

	if (span)
	{
		span.onclick = function()
		{
			modal.style.display = "none";
		}
	}

	window.onclick = function(event)
	{
		if (event.target == modal)
		{
			modal.style.display = "none";
		}
	}
});
