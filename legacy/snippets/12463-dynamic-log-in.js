<!--
Elementor Custom Code snippet (post_type elementor_snippet) exported read-only from staging
post_id: 12463
title: dynamic log in
status: draft
location (_elementor_location): elementor_body_end
priority (_elementor_priority): 1
conditions (_elementor_conditions): ["include/general"]
conditions raw: a:1:{i:0;s:15:"include/general";}
extra options (_elementor_extra_options): []
code bytes (LENGTH(_elementor_code)): 563
Everything below the END HEADER line is the _elementor_code meta value verbatim (post_content is empty for these posts).
-->
<!-- END HEADER -->
<script>
window.addEventListener("load", () => {
  const body = document.body;
  const isUserLoggedIn = body.classList.contains("logged-in");
  const logInElement = document.querySelector("#log-in a")
  const baseUrl = window.location.origin;

  console.log("User logged in:", isUserLoggedIn);
  console.log("Base URL:", baseUrl);

  if (logInElement) {
    logInElement.href = isUserLoggedIn
      ? `${baseUrl}/account`
      : `${baseUrl}/log-in`;
  } else {
    console.warn("⚠️ Element with ID 'log-in' not found.");
  }
});
</script>
