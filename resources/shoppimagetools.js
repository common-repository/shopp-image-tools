jQuery.noConflict();

jQuery(document).ready(function($){
	var allInputs = $("input#converter").add("input#orphancleanup").add("#smartcleanup");

	$("input#converter").add("input#orphancleanup").click(function(event) {
		var toolName = $(this).attr("name");
		$(allInputs).attr("disabled", "disabled");

		// Create #conversionlog if it doesn't already exist
		if (document.getElementById("conversionlog") === null) {
			$(".wrap.shopp").append('<div id="conversionlog"> <ol> </ol> </div>'
				+'<div id="work-meter-holder"> <div id="work-meter"> <div id="work-indicator">'
				+'<div id="worker-droid"> </div> </div> </div> </div>');
		}

		// Definitions for the worker droid animation
		var working = false;
		var phase = 0;
		var container = $("#work-indicator");
		var droid = $("#worker-droid");
		var width = $(container).css("width").replace("px", "");

		// Reinitiates animation if work is still in progress
		function reAnimate() {
			// Don't continue if we have stopped work - but wait until we are at
			// a "resting" phase
			if (working === false && (phase === 0 || phase === 2)) return;
			animate();
			if (++phase === 4) phase = 0;
		}

		// Animates the worker droid
		function animate() {
			droid.clearQueue();

			var property = "right";
			var change = "-=";

			if (phase === 1 || phase === 2) property = "left";
			if (phase === 1 || phase === 3) change = "+=";

			change = change+width;

			var map = Object();
			map[property] = change;

			$(droid).animate(map, 660, reAnimate);
		}

		// Smooth out unit differences
		left = $(droid).css("left", "0px");
		right = $(droid).css("right", width+"px");

		// Refresh the summary table
		function summaryTableRefresh() {
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: { action: "shopp_image_tools", job: 'summaryupdate', check: checkStr },
				success: function(data) {
					if (typeof data.db === "number") $("td.db-total").html(data.db);
					if (typeof data.fs === "number") $("td.fs-total").html(data.fs);
					if (typeof data.other === "number") $("td.other-total").html(data.other);
				},
				dataType: "json"
			});
		}

		// Get reference to the log itself (ordered list) as well as the container
		var logbook = $("#conversionlog");
		var log = $("#conversionlog ol");

		// Prepare our communication loop
		var task = "initialize";
		var checkStr = $("#checkstring").val();
		var noDefaultAction = true;

		// Remember smartcleanup setting from when the task started
		var smartcleanup;

		// Handle the response from the converter
		function response(data) {
			if (data !== null && data !== 0 && data !== undefined) {
				// Update the log
				if (data.message.indexOf("---") !== -1) {
					logEntries = data.message.split("---");
					for (var i = 0; i < logEntries.length; i++) {
						$(log).append("<li>" + logEntries[i] + "</li>");
					}
				}
				else {
					$(log).append("<li>" + data.message + "</li>");
				}

				// Scroll the container to the most recent log message
				$(logbook).animate({
					scrollTop: $(logbook)[0].scrollHeight
				}, 100);

				// Repeat the process if we received a "continue" status
				if (data.status === "continue") request("process");
				if (data.status === "stop") {
					// Clear the disabled status
					$(allInputs).removeAttr("disabled");

					// Stop worker animation
					if (working === true)
						working = false;
				}

				summaryTableRefresh(); // Refresh the totals in the summary table
			};
		}

		// Send a message to the converter
		function request(task) {
			// Look for smartcleanup setting
			if (typeof smartcleanup === "undefined")
				smartcleanup = $("#smartcleanup").attr("checked") === "checked" ? "1" : "0";

			var request = {
				action: "shopp_image_tools",
				job: toolName,
				task: task,
				cleanup: smartcleanup,
				check: checkStr
			};

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: request,
				success: response,
				dataType: "json"
			});

			// Worker animation
			if (working === false) {
				working = true;
				reAnimate();
			}
		}

		request("initialize");
		event.preventDefault();
	});
});