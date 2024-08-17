/*



	File: king-content/king-ask.js
	Version: See define()s at top of king-include/king-base.php
	Description: Javascript for ask page and question editing, including tag auto-completion


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: LICENCE.html
*/

function qa_title_change(value)
{
	qa_ajax_post('asktitle', {title:value}, function(lines) {
		if (lines[0]=='1') {
			if (lines[1].length) {
				qa_tags_examples=lines[1];
				qa_tag_hints(true);
			}

			if (lines.length>2) {
				var simelem=document.getElementById('similar');
				if (simelem)
					simelem.innerHTML=lines.slice(2).join('\n');
			}

		} else if (lines[0]=='0')
			alert(lines[1]);
		else
			qa_ajax_error();
	});

	qa_show_waiting_after(document.getElementById('similar'), true);
}

function qa_html_unescape(html)
{
	return html.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
}

function qa_html_escape(text)
{
	return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function qa_tag_click(link)
{
	var elem=document.getElementById('tags');
	var parts=qa_tag_typed_parts(elem);

	// removes any HTML tags and ampersand
	var tag=qa_html_unescape(link.innerHTML.replace(/<[^>]*>/g, ''));

	var separator=qa_tag_onlycomma ? ', ' : ' ';

	// replace if matches typed, otherwise append
	var newvalue=(parts.typed && (tag.toLowerCase().indexOf(parts.typed.toLowerCase())>=0))
		? (parts.before+separator+tag+separator+parts.after+separator) : (elem.value+separator+tag+separator);

	// sanitize and set value
	if (qa_tag_onlycomma)
		elem.value=newvalue.replace(/[\s,]*,[\s,]*/g, ', ').replace(/^[\s,]+/g, '');
	else
		elem.value=newvalue.replace(/[\s,]+/g, ' ').replace(/^[\s,]+/g, '');

	elem.focus();
	qa_tag_hints();

	return false;
}

function qa_tag_hints(skipcomplete)
{
	var elem=document.getElementById('tags');
	var html='';
	var completed=false;

	// first try to auto-complete
	if (qa_tags_complete && !skipcomplete) {
		var parts=qa_tag_typed_parts(elem);

		if (parts.typed) {
			html=qa_tags_to_html((qa_html_unescape(qa_tags_examples+','+qa_tags_complete)).split(','), parts.typed.toLowerCase());
			completed=html ? true : false;
		}
	}

	// otherwise show examples
	if (qa_tags_examples && !completed)
		html=qa_tags_to_html((qa_html_unescape(qa_tags_examples)).split(','), null);

	// set title visiblity and hint list
	document.getElementById('tag_examples_title').style.display=(html && !completed) ? '' : 'none';
	document.getElementById('tag_complete_title').style.display=(html && completed) ? '' : 'none';
	document.getElementById('tag_hints').innerHTML=html;
}

function qa_tags_to_html(tags, matchlc)
{
	var html='';
	var added=0;
	var tagseen={};

	for (var i=0; i<tags.length; i++) {
		var tag=tags[i];
		var taglc=tag.toLowerCase();

		if (!tagseen[taglc]) {
			tagseen[taglc]=true;

			if ( (!matchlc) || (taglc.indexOf(matchlc)>=0) ) { // match if necessary
				if (matchlc) { // if matching, show appropriate part in bold
					var matchstart=taglc.indexOf(matchlc);
					var matchend=matchstart+matchlc.length;
					inner='<span style="font-weight:normal;">'+qa_html_escape(tag.substring(0, matchstart))+'<b>'+
						qa_html_escape(tag.substring(matchstart, matchend))+'</b>'+qa_html_escape(tag.substring(matchend))+'</span>';
				} else // otherwise show as-is
					inner=qa_html_escape(tag);

				html+=qa_tag_template.replace(/\^/g, inner.replace('$', '$$$$'))+' '; // replace ^ in template, escape $s

				if (++added>=qa_tags_max)
					break;
			}
		}
	}

	return html;
}

function qa_caret_from_end(elem)
{
	if (document.selection) { // for IE
		elem.focus();
		var sel=document.selection.createRange();
		sel.moveStart('character', -elem.value.length);

		return elem.value.length-sel.text.length;

	} else if (typeof(elem.selectionEnd)!='undefined') // other browsers
		return elem.value.length-elem.selectionEnd;

	else // by default return safest value
		return 0;
}

function qa_tag_typed_parts(elem)
{
	var caret=elem.value.length-qa_caret_from_end(elem);
	var active=elem.value.substring(0, caret);
	var passive=elem.value.substring(active.length);

	// if the caret is in the middle of a word, move the end of word from passive to active
	if (
		active.match(qa_tag_onlycomma ? /[^\s,][^,]*$/ : /[^\s,]$/) &&
		(adjoinmatch=passive.match(qa_tag_onlycomma ? /^[^,]*[^\s,][^,]*/ : /^[^\s,]+/))
	) {
		active+=adjoinmatch[0];
		passive=elem.value.substring(active.length);
	}

	// find what has been typed so far
	var typedmatch=active.match(qa_tag_onlycomma ? /[^\s,]+[^,]*$/ : /[^\s,]+$/) || [''];

	return {before:active.substring(0, active.length-typedmatch[0].length), after:passive, typed:typedmatch[0]};
}

function qa_category_select(idprefix, startpath)
{
	var startval=startpath ? startpath.split("/") : [];
	var setdescnow=true;

	for (var l=0; l<=qa_cat_maxdepth; l++) {
		var elem=document.getElementById(idprefix+'_'+l);

		if (elem) {
			if (l) {
				if (l<startval.length && startval[l].length) {
					var val=startval[l];

					for (var j=0; j<elem.options.length; j++)
						if (elem.options[j].value==val)
							elem.selectedIndex=j;
				} else
					var val=elem.options[elem.selectedIndex].value;
			} else
				val='';

			if (elem.qa_last_sel!==val) {
				elem.qa_last_sel=val;

				var subelem=document.getElementById(idprefix+'_'+l+'_sub');
				if (subelem)
					subelem.parentNode.removeChild(subelem);

				if (val.length || (l==0)) {
					subelem=elem.parentNode.insertBefore(document.createElement('span'), elem.nextSibling);
					subelem.id=idprefix+'_'+l+'_sub';
					qa_show_waiting_after(subelem, true);

					qa_ajax_post('category', {categoryid:val},
						(function(elem, l) {
							return function(lines) {
								var subelem=document.getElementById(idprefix+'_'+l+'_sub');
								if (subelem)
									subelem.parentNode.removeChild(subelem);

								if (lines[0]=='1') {
									elem.qa_cat_desc=lines[1];

									var addedoption=false;

									if (lines.length>2) {
										var subelem=elem.parentNode.insertBefore(document.createElement('span'), elem.nextSibling);
										subelem.id=idprefix+'_'+l+'_sub';
										subelem.innerHTML=' ';

										var newelem=elem.cloneNode(false);

										newelem.name=newelem.id=idprefix+'_'+(l+1);
										newelem.options.length=0;

										if (l ? qa_cat_allownosub : qa_cat_allownone)
											newelem.options[0]=new Option(l ? '' : elem.options[0].text, '', true, true);

										for (var i=2; i<lines.length; i++) {
											var parts=lines[i].split('/');

											if (String(qa_cat_exclude).length && (String(qa_cat_exclude)==parts[0]))
												continue;

											newelem.options[newelem.options.length]=new Option(parts.slice(1).join('/'), parts[0]);
											addedoption=true;
										}

										if (addedoption) {
											subelem.appendChild(newelem);
											qa_category_select(idprefix, startpath);

										}

										if (l==0) {
											elem.style.display='none';
											elem.removeAttribute('required');
										}
									}

									if (!addedoption)
										set_category_description(idprefix);

								} else if (lines[0]=='0')
									alert(lines[1]);
								else
									qa_ajax_error();
							}
						})(elem, l)
					);

					setdescnow=false;
				}

				break;
			}
		}
	}

	if (setdescnow)
		set_category_description(idprefix);
}

function set_category_description(idprefix)
{
	var n=document.getElementById(idprefix+'_note');

	if (n) {
		desc='';

		for (var l=1; l<=qa_cat_maxdepth; l++) {
			var elem=document.getElementById(idprefix+'_'+l);

			if (elem && elem.options[elem.selectedIndex].value.length)
				desc=elem.qa_cat_desc;
		}

		n.innerHTML=desc;
	}
}


function video_add(item, value) {
	var params = {};
	params.url = value;
	qa_ajax_post('video_add', params, function(lines) {
		if (lines[0] == '1') {
			var x = item.parentNode; 
    		var y = x.querySelector('#videoembed');
			y.innerHTML = lines[1];
			console.log(x);		
		}
	});
	qa_show_waiting_after(item, true);
}


function aigenerate(item) {
    var params = {};
    const input = document.getElementById("ai-box");
    const { value } = input;
    const nprompt = document.getElementById("n_prompt");
    if (nprompt) {
        var npvalue = nprompt.value;
    } else {
        var npvalue = '';
    }

    const selectElement = document.getElementById("ai-select");
	const selectedOption = selectElement.options[selectElement.selectedIndex];
    const selectedValue = selectedOption.value;
    const selectedLabel = selectedOption.textContent;
    if (!value.trim()) {
        return;
    }
    var radioBut = $("input:radio[name=aisize]:checked").val();
    var aistyle = $("input:radio[name=aistyle]:checked").val();

    item.disabled = true;
    input.disabled = true;
    item.classList.add("loading");
    params.input  = value;
    params.npvalue = npvalue;
    params.selectElement = selectedValue;
    params.radioBut = radioBut;
    params.aistyle = aistyle;

	var uniqueid = Math.random().toString(36).substring(2);
	uniqueid = uniqueid.replace(/[0-9]/g, ''); // Remove any digits
	
    // Your existing code
    qa_ajax_post('aigenerate', params, function(lines) {
		const aierror = document.getElementById("ai-error");
        if (lines[0] == '1') {

            response = JSON.parse(lines[1]);
            if (response.success) {
                const results = document.getElementById("ai-results");
                const sresult = response.message.out;
                const format = response.message.format;
                if (format === 'bas') {
                    var images = sresult.map(base64 => `<div class="ai-imgs" id="ai-imgs"><img src="data:image/png;base64, ${base64}"/><a class="aidownl" title="Download" href="" download><i class="fa-solid fa-download"></i></a><button class="delete-btn loading"></button></div>`);
                } else {
                    var images = sresult.map(url => `<div class="ai-imgs" id="ai-imgs"><img src="${url}"/><a class="aidownl" title="Download" href="" download target="_blank"><i class="fa-solid fa-download"></i></a><button class="delete-btn loading"></button></div>`);
                }
                results.style.display = 'flex';
				
                let html = '<div class="ai-result '+uniqueid+'" id="'+uniqueid+'">';
				html += images.join('');
                html += '<div class="ai-result-up">';
				html += '<button class="aipublish" onclick="aipublish(this)" data-id="'+uniqueid+'" disabled>Publish</button>';

                if (selectedValue) {
                    html += '<span>'+selectedLabel+'</span>';
                }
                html += '<span>' + radioBut + '</span>';
                if (aistyle) {
                    html += '<span>' + aistyle + '</span>';
					html += '<input type="hidden" class="'+uniqueid+'" value="'+params.aistyle+'" name="stle">';
                }
				html += '<input type="hidden" class="'+uniqueid+'" value="'+params.input+'" name="pcontent">';
				html += '<input type="hidden" class="'+uniqueid+'" value="'+params.npvalue+'" name="npromp">';
				html += '<input type="hidden" class="'+uniqueid+'" value="'+params.radioBut+'" name="asize">';
				html += '<input type="hidden" class="'+uniqueid+'" value="'+params.selectElement+'" name="wai">';
                html += '</div>';
				
                html += '</div>';
                results.insertAdjacentHTML('afterbegin', html);
                input.disabled = false;
                item.disabled = false;
                item.classList.remove("loading");

                // Get all image elements
                var imageElements = document.querySelectorAll('.ai-imgs');

                // Call uploadai for each image URL
                sresult.forEach(function(url, index) {
                    uploadai(url, format, imageElements[index], uniqueid);
                });
            } else {
                aierror.style.display = 'block';
                aierror.innerHTML += response.message;
                input.disabled = false;
                item.disabled = false;
                item.classList.remove("loading");
            }
        } else {
				response = JSON.parse(lines[1]);
				aierror.style.display = 'block';
				aierror.innerHTML += response.message;
                input.disabled = false;
                item.disabled = false;
                item.classList.remove("loading");
		}
    });
}



function uploadai(imageUrl, format, imageElement, uniqueid) {
    // Define parameters for the other action
    var params = {};
    params.format = format;
    params.iurl = imageUrl;

    qa_ajax_post('uploadai', params, function(lines) {
        var response = JSON.parse(lines[1]);

        if (response) {
            // Extract the image ID from the server response
            var imageId = response.main;
			var thumb = response.thumb;
            var button = document.querySelector('button[data-id="' + uniqueid + '"]');
			if (button) {
				button.removeAttribute('disabled');
			}
	        
            // Add the image ID to the delete button
            var deleteBtn = imageElement.querySelector(".delete-btn");
			var idown = imageElement.querySelector(".aidownl");
            if (deleteBtn) {
				deleteBtn.classList.remove("loading");
				deleteBtn.setAttribute('onclick', 'deleteAiImage(event, ' + imageId + ', ' + thumb + ', "' + uniqueid + '")');
				idown.href = response.url;
				var inp = document.createElement("INPUT");
                var radio = document.createElement("INPUT");
                var label = document.createElement("label");
				
                inp.setAttribute("type", "hidden");
                inp.setAttribute("name", "submit_image[]");
				inp.setAttribute("class", uniqueid);
                inp.setAttribute("id", "submit_image_" + uniqueid);
                inp.setAttribute("value", imageId);
                radio.setAttribute("value", thumb);
                radio.setAttribute("type", "radio");
                radio.setAttribute("name", "thumbz" +uniqueid);
                radio.setAttribute("id", "thumb_" + thumb);
                radio.setAttribute("class", "thumb-radio hide");
                radio.setAttribute("checked", true);
                label.setAttribute("title", "set as thumb");
                label.setAttribute("class", "thumb-radio-label");
                label.setAttribute("for", "thumb_" + thumb);


                deleteBtn.parentNode.appendChild(inp);
                deleteBtn.parentNode.appendChild(radio);
                deleteBtn.parentNode.appendChild(label);
            }
        }
    });
}

function deleteAiImage(event, imageId, thumb, uniqueid) {
    var params = {};

    params.thumbid = imageId;
    params.fileid = thumb;
    qa_ajax_post('mdelete', params, function(lines) {
        if (lines[0] == '0') {
            const cInput = document.getElementById('thumb_' + thumb);
            const closestLabel = document.querySelector('label.thumb-radio-label');
            if (closestLabel && cInput.checked) {
                closestLabel.click();
                const inputId = closestLabel.getAttribute('for');
                const correspondingInput = document.getElementById(inputId);
                if (correspondingInput) {
                    correspondingInput.checked = true;
                }
            }
            const parentDiv = event.target.parentElement;
            parentDiv.remove();

            const aiResultDiv = document.getElementById(uniqueid);

            if (aiResultDiv && aiResultDiv.querySelectorAll('.ai-imgs').length === 0) {
                aiResultDiv.style.display = 'none';
            }
        }
    });
}


function changeOption(event) {
    const selectBox = event.target.value; 
    const selectedValue = document.getElementById("desizes");
    var firstTabLink = document.querySelector('#ssize li:first-child a');


    selectedValue.className = selectBox;
    document.getElementById("aisize3").checked = true;

    firstTabLink.click();
}


function aipublish(element) {
    var targetElement = document.querySelector('.ai-create'); // Corrected selector
	const uniqueid = element.getAttribute('data-id');
    targetElement.classList.toggle('active');

	var htmlElement = document.querySelector('html');
        if (htmlElement.style.marginRight === '17px') {
            htmlElement.style.marginRight = '';
            htmlElement.style.overflow = '';
        } else {
            htmlElement.style.marginRight = '17px';
            htmlElement.style.overflow = 'hidden';
        }

	if (uniqueid) {
        var clonedInputsDiv = document.getElementById('ai-clone');
		clonedInputsDiv.innerHTML = '';
		var econ = document.getElementById('error-container');
		econ.innerHTML = '';
		var title = document.getElementById('title');
		title.value = '';
		var tags =  document.getElementById('tags');
		tags.value = '';
        var inputsToClone = document.querySelectorAll('.ai-result input.' + uniqueid);

        inputsToClone.forEach(function(input) {
            var clonedInput = input.cloneNode(true); // true to clone also the descendants
            clonedInputsDiv.appendChild(clonedInput); // Append the cloned input to the target div
        });
		var checkedRadioValue = document.querySelector('input[name="thumbz'+uniqueid+'"]:checked').value;
        document.getElementById('thumb_ai').value = checkedRadioValue;
		document.getElementById('uniqueid').value = uniqueid;

	}
}
function submitAiform(event) {
    event.preventDefault();
	var submitButton = document.getElementById("submitButton");
    submitButton.disabled = true;
    var form = document.getElementById("ai-form");
    var formData = new FormData(form);

    var xhr = new XMLHttpRequest();
    xhr.open("POST", form.action, true);

    // Set up the onload callback function
    xhr.onload = function() {

        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.status === 'success') {
                var targetElement = document.querySelector('.ai-create');

                targetElement.classList.toggle('active');

				var htmlElement = document.querySelector('html');
				if (htmlElement.style.marginRight === '17px') {
					htmlElement.style.marginRight = '';
					htmlElement.style.overflow = '';
				} else {
					htmlElement.style.marginRight = '17px';
					htmlElement.style.overflow = 'hidden';
				}

				var uniqueid = formData.get('uniqueid');
				var divElement = document.querySelector('.ai-result.' + uniqueid);
				divElement.innerHTML = '<div class="ai-published"><i class="fa-solid fa-check"></i><h3>'+response.message+'</h3></div><div class="ai-result-up"><a class="aipublish" href="'+response.url+'" target="_blank">'+response.message2+'</a></div>';
				submitButton.disabled = false;
            } else {
                // Display error message at the top of the form
                var errorContainer = document.getElementById("error-container");
                errorContainer.innerHTML = '';
                for (var key in response.message) {
                    if (response.message.hasOwnProperty(key)) {
                        var errorMessage = response.message[key];
                        errorContainer.innerHTML += '<div class="king-form-tall-error">' + errorMessage + '</div>';
                    }
                }
				submitButton.disabled = false;
            }
        } else {
            // Handle any errors here
            console.error('Form submission failed:', xhr.statusText);
        }
    };

    // Set up the onerror callback function
    xhr.onerror = function() {
        // Handle any network errors here
        console.error('Network error occurred');
    };

    // Send the form data
    xhr.send(formData);
}


function aipromter(elem) {
	var params = {};
    const input = document.getElementById("ai-box");
    const { value } = input;
	params.prompt = value;
    elem.disabled = true;
    elem.classList.add("loading");


	qa_ajax_post('prompter', params, function(lines) {
		var response = JSON.parse(lines[1]);
        if (response) {
			
			if (response.success) {
                const sresult = response.message;
                let index = 0;
                input.value = "";
                const typingInterval = setInterval(() => {
                    const char = sresult.charAt(index);
                    input.value += char; // Append char to textarea value
                    index++;
                    input.style.height = "auto"; // Reset height to auto
                    input.style.height = `${input.scrollHeight}px`;
                    if (index >= sresult.length) {
                        clearInterval(typingInterval);
                        // Enable the submit button and remove loading class
                        elem.disabled = false;
						elem.classList.remove("loading");
                    }
                }, 20);

            } else {
                console.log(response.data);
                input.value += response.data; // Append response data to textarea value
                // Enable the submit button and remove loading class
				elem.disabled = false;
				elem.classList.remove("loading");
            }			
		}
	});

}

function adjustHeight(textarea) {
    textarea.style.height = "auto"; // Reset height to auto
    textarea.style.height = `${textarea.scrollHeight}px`; // Set the height to fit the content
}

function aiask(elem) {
    var params = {};
    const input = document.getElementById("ai-box");
    const { value } = input;
    params.prompt = value;
    elem.disabled = true;
    elem.classList.add("loading");

    const formatSelect = document.getElementById("ai-format");
    const selectedFormat = formatSelect.value;

    if (selectedFormat === "content") {
        qa_ajax_post('aiask', params, function(lines) {
            var response = JSON.parse(lines[1]);
            console.log(response);
            if (response) {
                if (response.success) {
                    const sresult = response.message;
                    const chatBox = document.querySelector("#pcontent p");
                    let index = 0;
                    const typingInterval = setInterval(() => {
                        const char = sresult.charAt(index);
                        const element = char === '\n' ? "<br>" : char;
                        chatBox.insertAdjacentHTML('beforebegin', element);
                        index++;
                        if (index >= sresult.length) {
                            clearInterval(typingInterval);
                            elem.disabled = false;
                            elem.classList.remove("loading");
                        }
                    }, 20);
                } else {
                    input.value += response.data; // Append response data to textarea value
                    // Enable the submit button and remove loading class
                    elem.disabled = false;
                    elem.classList.remove("loading");
                }
            }
        });
    } else if (selectedFormat === "thumb") {
		var model = elem.getAttribute("data-model");
		params.input  = value;
		params.selectElement = model;
		params.radioBut = '1024x1024';

		var uniqueid = Math.random().toString(36).substring(2);
		uniqueid = uniqueid.replace(/[0-9]/g, '');
        qa_ajax_post('aigenerate', params, function(lines) {
            var response = JSON.parse(lines[1]);
            console.log(response);
            if (response) {
                if (response.success) {

							const results = document.getElementById("newsthumb");
							const sresult = response.message.out;
							const format = response.message.format;
							if (format === 'bas') {
								var images = sresult.map(base64 => `<div class="ai-img dz-preview dz-processing dz-image-preview dz-success dz-complete `+uniqueid+`" id="ai-imgs"><img src="data:image/png;base64, ${base64}"/><div class="delete-btn loading"></div><a class="aidownl" title="Download" href="" download><i class="fa-solid fa-download"></i></a></div>`);
							} else {
								var images = sresult.map(url => `<div class="ai-img dz-preview dz-processing dz-image-preview dz-success dz-complete `+uniqueid+`" id="ai-imgs"><img src="${url}"/><div class="delete-btn loading"></div><a class="aidownl" title="Download" href="" download><i class="fa-solid fa-download"></i></a></div>`);
							}
							results.style.display = 'flex';
							
							
							 let html = images.join('');
							
							results.insertAdjacentHTML('afterbegin', html);
							elem.disabled = false;

							elem.classList.remove("loading");
			
							// Get all image elements
							var imageElements = document.querySelectorAll('.ai-img');
			
							// Call uploadai for each image URL
							sresult.forEach(function(url, index) {
								uploadai(url, format, imageElements[index], uniqueid);
							});

                } else {
                    input.value += response.data; // Append response data to textarea value
                    // Enable the submit button and remove loading class
                    elem.disabled = false;
                    elem.classList.remove("loading");
                }
            }
        });
    }
}

