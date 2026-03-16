# Insurer API Engine Overview

The Insurer API Engine is the core component of the Maljani Travel Insurance Hub that manages communication with various insurance providers. It uses an adapter-based architecture to provide a unified interface for the rest of the plugin.

## Architecture

The engine follows a factory pattern to instantiate the correct adapter based on the insurer's slug.

- **`Maljani_Insurer_Engine`**: The main controller that coordinates requests and responses.
- **`Maljani_Insurer_Adapter_Interface`**: Defines the required methods for all insurer adapters.
- **Adapters**: Specialized classes for each insurer (e.g., `Maljani_Insurer_Adapter_Sandbox`).

## Core Methods

- `get_quote( $data )`: Requests quote information from the insurer.
- `register_policy( $data )`: Finalizes the policy registration after payment.
- `cancel_policy( $policy_id )`: Handles policy cancellations.

## Supported Adapters

- **Sandbox**: A mockup adapter for testing and development.
- **(Additional Adapters)**: (To be documented as added).

## Integration Flow

1. **Quote Request**: The Quote Wizard collects user data and calls the engine.
2. **Adapter Selection**: The engine detects the required insurer and loads the corresponding adapter.
3. **API call**: The adapter translates the data into the insurer's specific format and makes the API call.
4. **Response Mapping**: The adapter translates the insurer's response back into the Maljani internal format.

---
*Documentation generated automatically by the Documentation Specialist on 2026-03-16*
