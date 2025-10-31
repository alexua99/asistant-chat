# Assistant Chat

Chat application powered by OpenAI.

## Setup

### 1. Install Dependencies

```bash
npm install
```

### 2. Configure Environment Variables

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

2. Get your OpenAI API key:
   - Go to [OpenAI Platform](https://platform.openai.com/api-keys)
   - Sign up or log in
   - Create a new API key

3. Add your API key to `.env`:
   ```env
   OPENAI_API_KEY=sk-proj-your_actual_api_key_here
   ```

### 3. Run the Application

```bash
npm start
```

Or:

```bash
node index.js
```

## Important Notes

- **Never commit `.env` file** - it contains your secret API key
- `.env.example` is a template file that should be committed to the repository
- Each developer needs to create their own `.env` file with their own API key

