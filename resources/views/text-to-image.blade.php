<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Text to Presentation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f9f9f9;
        }
        h1 {
            text-align: center;
        }
        form {
            text-align: center;
            margin-bottom: 30px;
        }
        textarea {
            width: 80%;
            height: 100px;
            margin-bottom: 10px;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        #svg-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        svg {
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
    <h1>Text to Presentation</h1>

    <form id="presentationForm">
        <textarea name="content" id="content" placeholder="Enter your text here..." required></textarea>
        <button type="submit">Generate</button>
    </form>

    <div id="svg-container">
    </div>

    <script>
        document.getElementById("presentationForm").addEventListener("submit", async function (e) {
            e.preventDefault();

            const content = document.getElementById("content").value;

            try {
                const response = await fetch("{{ route('generate.presentation') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ content }),
                });

                if (!response.ok) {
                    throw new Error("Failed to generate presentation.");
                }

                const data = await response.json();
                await updateSVG(data);
                // generateSvg(data);
            } catch (error) {
                alert(error.message);
            }
        });

        async function wrapText(svgTextElement, text, maxWidth, baseHeight, lineHeight, alignment = 'lt') {
            const words = text.split(' ');
            let line = '';
            let dy = 0;
            const lines = [];

            if (!svgTextElement.parentNode) {
                throw new Error("svgTextElement must be appended to the DOM before calling wrapText.");
            }

            // Clear any existing content
            svgTextElement.textContent = '';

            // Split text into lines based on maxWidth
            while (words.length > 0) {
                const testLine = line + words[0] + ' ';

                // Measure the width of the test line
                const tempTspan = document.createElementNS("http://www.w3.org/2000/svg", "tspan");
                tempTspan.textContent = testLine;
                svgTextElement.appendChild(tempTspan);
                const testWidth = tempTspan.getBBox().width;
                svgTextElement.removeChild(tempTspan);

                if (testWidth > maxWidth && line !== '') {
                    lines.push(line.trim());
                    line = words.shift() + ' ';
                } else {
                    line = testLine;
                    words.shift();
                }
            }
            if (line.trim()) {
                lines.push(line.trim());
            }

            // Calculate vertical alignment adjustments
            const totalHeight = lines.length * lineHeight;
            let startY = 0;

            if (alignment[1] === 'b') {
                startY = 0 - totalHeight + baseHeight;
            } else if (alignment[1] === 'c') {
                startY = 0 - totalHeight / 2 + baseHeight / 2 + lineHeight / 2;
            } else if (alignment[1] === 't') {
                startY = 0 + lineHeight / 2;
            }

            // Add tspans for each line
            lines.forEach((lineText, index) => {
                const tspan = document.createElementNS("http://www.w3.org/2000/svg", "tspan");
                tspan.textContent = lineText;
                tspan.setAttribute("dy", index === 0 ? 0 : lineHeight);
                svgTextElement.appendChild(tspan);
            });

            // Apply horizontal alignment
            const tspans = svgTextElement.querySelectorAll("tspan");
            tspans.forEach(tspan => {
                let adjustedX = 0;
                const lineWidth = tspan.getBBox().width;

                if (alignment[0] === 'c') {
                    adjustedX = 0 + (maxWidth - lineWidth) / 2;
                } else if (alignment[0] === 'r') {
                    adjustedX = 0 + maxWidth - lineWidth;
                }

                tspan.setAttribute("x", adjustedX);
            });

            // Adjust vertical alignment
            tspans.forEach((tspan, index) => {
                if (index === 0) {
                    tspan.setAttribute("y", startY);
                }
            });
        }


        async function updateSVG(data) {
            const svgList = ["svg_template.svg", "sequence_journey.svg", "sequence_spring.svg", "sequence_strip.svg"];

            const container = document.getElementById('svg-container');
            // Clear the container before adding new SVGs
            container.innerHTML = '';

            for(let i = 0; i < svgList.length; i ++){
                // Path to your SVG file in the "view" directory
                const response = await fetch(svgList[i]);
                if (!response.ok) throw new Error('SVG file not found');

                // Get SVG content as text
                const svgText = await response.text();

                // Create a new div to hold the SVG and insert the SVG into the DOM
                const svgWrapper = document.createElement('div');
                svgWrapper.innerHTML = svgText.trim(); // Ensure no extra spaces
                container.appendChild(svgWrapper);

                // Access the SVG element inside the wrapper
                const svg = svgWrapper.querySelector('svg');
                if (!svg) {
                    console.warn(`No SVG element found in: ${svgList[i]}`);
                    continue;
                }

                // Update Title
                const titleElements = svg.querySelectorAll('[id^="tx-"][id*="-title"]'); // Selects IDs starting with "tx-" and containing "-title"
                titleElements.forEach(async (titleElement) => {
                    if (titleElement) {
                        const titleElem = titleElement.tagName.toLowerCase() === 'g'
                            ? titleElement.querySelector("path") || titleElement
                            : titleElement;
                        const width = titleElement.getBBox().width; // Get available width
                        const x = parseFloat(titleElem.getAttribute("x")) || 0; // Default x

                        const titleText = document.createElementNS("http://www.w3.org/2000/svg", "text");
                        const transform = titleElem.getAttribute("transform");
                        if (transform) {
                            titleText.setAttribute("transform", transform);
                        }
                        titleText.setAttribute("x", 180);
                        titleText.setAttribute("y", 30);
                        titleText.setAttribute("font-size", "24");
                        titleText.setAttribute("font-family", "Roboto");
                        titleText.setAttribute("font-weight", "700");
                        titleText.setAttribute("fill", "#000");
                        titleText.textContent = data.Title;

                        const parent = titleElement.parentNode;
                        parent.replaceChild(titleText, titleElement);
                    }
                });

                // Update content sections
                // Select all elements that match the broad pattern
                const TDescElements = document.querySelectorAll('[id^="tx-"][id*="-desc"]');
                const TDescTitlelements = document.querySelectorAll('[id^="tx-"]');

                // Filter elements using a regex
                const descElements = Array.from(TDescElements).filter(el => /\d+-desc$/.test(el.id));
                const descTitleElements = Array.from(TDescTitlelements).filter(el => /^tx-[a-z]{2}-\d+$/.test(el.id));

                // const descElements = svg.querySelectorAll('[id^="tx-"][id*="-title"]');
                // const descTitleElements = svg.querySelectorAll('[data-entity-classes="DescTitle"]');
                data.content.forEach(async (item, index) => {
                    const descElement = descElements[index];
                    const descTitleElement = descTitleElements[index];

                    // Update the `descElement` section of your code
                    if (descElement) {
                        const descText = document.createElementNS("http://www.w3.org/2000/svg", "text");
                        const transform = descElement.getAttribute("transform");
                        if (transform) {
                            descText.setAttribute("transform", transform);
                        }
                        descText.setAttribute("font-size", "16");
                        descText.setAttribute("font-family", "Roboto");
                        descText.setAttribute("fill", "#000");

                        const width = descElement.getBBox().width; // Get available width
                        const height = descElement.getBBox().height; // Get available width
                        const x = parseFloat(descElement.getAttribute("x")) || 0; // Default x
                        const y = parseFloat(descElement.getAttribute("y")) || 0; // Default y

                        // Temporarily append the new text element to the DOM for measurements
                        descElement.parentNode.appendChild(descText);

                        console.log(y);
                        // Add wrapped text to descText
                        await wrapText(descText, item.Description, width, height, 18, descElement.getAttribute('id').slice(3,5));

                        // Replace the old description element with the new one
                        descElement.parentNode.replaceChild(descText, descElement);
                    }

                    // Update the title within the content section
                    if (descTitleElement) {
                        const titleText = descTitleElement.tagName.toLowerCase() === 'g'
                            ? descTitleElement.querySelector("path") || descTitleElement
                            : descTitleElement;

                        const descTitleText = document.createElementNS("http://www.w3.org/2000/svg", "text");
                        const transform = titleText.getAttribute("transform");
                        if (transform) {
                            descTitleText.setAttribute("transform", transform);
                        }
                        descTitleText.setAttribute("font-size", "20");
                        descTitleText.setAttribute("font-weight", "700");
                        descTitleText.setAttribute("font-family", "Roboto");
                        descTitleText.setAttribute("fill", "#000");

                        // Get the width of the descTitleElement
                        const titleWidth = titleText.getBBox().width; // Provide a default width if 0
                        const titleHeight = titleText.getBBox().height; // Provide a default width if 0
                        const x = parseFloat(descTitleElement.getAttribute("x")) || 0; // Default x
                        const y = parseFloat(descTitleElement.getAttribute("y")) || 0; // Default y

                        // Temporarily append the new text element to the DOM for wrapping
                        descTitleElement.parentNode.appendChild(descTitleText);
                        console.log('y1',y);

                        // Apply the wrapping logic
                        await wrapText(descTitleText, item.DescTitle, titleWidth, titleHeight, 22,  descTitleElement.getAttribute('id').slice(3,5));

                        descTitleElement.parentNode.replaceChild(descTitleText, descTitleElement);
                    }
                });

                document.querySelectorAll('[id^="ic-"]').forEach(element => element.remove());
                document.querySelectorAll('[id^="bt-"]').forEach(element => element.remove());

            }
        }

    </script>
</body>
</html>
