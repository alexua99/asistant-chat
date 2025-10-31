import OpenAI from "openai";
import dotenv from "dotenv";

dotenv.config();

const apiKey = process.env.OPENAI_API_KEY;
if (!apiKey) {
    throw new Error("OPENAI_API_KEY is not set in environment variables");
}

const client = new OpenAI({ apiKey });

const response = await client.responses.create({
    model: "gpt-5-nano",
    input: "Просто напиши 'Привет'",
});


console.log(response.output_text);