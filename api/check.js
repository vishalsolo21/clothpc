import crypto from "crypto";

export default async function handler(req, res) {
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");

  if (req.method === "OPTIONS") {
    return res.status(200).end();
  }

  if (req.method !== "POST") {
    return res.status(405).json({
      error: "Method not allowed"
    });
  }

  const { phone } = req.body;

  try {
    const timestamp = Math.floor(Date.now() / 1000);

    const payload = {
      type: "swiggy",
      numbers: [phone],
      timestamp
    };

    // Replace this with your real secret/signing logic if needed
    const signature = "36d4a89f0eca5ce0cb88efd7c68fff2eb5067e8fbc6d24e4c40f7ed5bd409a8a";

    const response = await fetch("https://storelex.store/?api=1", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-App-Signature": signature
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json();

    const result = data.results?.[phone];

    return res.json({
      registered: result?.registered ?? false
    });

  } catch (error) {
    return res.status(500).json({
      error: "Server error"
    });
  }
}
