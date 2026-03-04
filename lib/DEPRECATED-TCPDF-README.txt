TCPDF library (DEPRECATED)
=================================

The bundled TCPDF library (lib/TCPDF-main) is no longer required by the
plugin's default verification-only flow. It was previously used to generate
policy PDF documents on-demand.

Recommended actions:
- If you no longer need PDF generation, remove the entire folder
  `lib/TCPDF-main/` from the repository to reduce package size.
- If you may need PDF generation later, move the folder to a safe backup
  location (e.g., `lib/deprecated/TCPDF-main/`) and document the reason.
- Before removing, ensure no other code depends on `tcpdf.php`. The plugin
  currently uses a verification-only flow and does not include TCPDF at
  runtime.

How to fully remove (example):

  1. From the plugin root run:

     rm -rf lib/TCPDF-main/

  2. Commit the change and test the plugin admin flows.

If you'd like, I can move the `lib/TCPDF-main` directory into
`lib/deprecated/` for you instead of deleting it.
