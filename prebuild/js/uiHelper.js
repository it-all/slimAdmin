if(document.getElementById("scrollingTableContainer")) {
    document.getElementById("scrollingTableContainer").addEventListener("scroll",function(){
        var translate = "translate(0,"+this.scrollTop+"px)";
        this.querySelector("thead").style.transform = translate;
    });
}

function toggleDisplay(el) {
    if (el.style.display == '' || el.style.display == 'none') {
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}

function togglePlusMinus(el) {
    el.innerHTML = (el.innerHTML == '+') ? '-' : '+';
}

/**
 *
 * @param element
 * @param attributes is array of Attribute objects
 */
function addAttributes(element, attributes) {
    for (var i = 0; i < attributes.length; i++) {
        element.setAttribute(attributes[i].name, attributes[i].value);
    }
}

function Attribute(name, value) {
    this.name = name;
    this.value = value;
}

function SelectOption(value, text, selected) {
    var selected = typeof selected !== 'undefined' ? selected : false;
    this.value = value;
    this.text = text;
    this.selected = selected;
}

function createInput(id, name, appendToElement, attributes) {
    var inputEl = document.createElement("input");
    inputEl.id = id;
    inputEl.name = name;
    if (attributes !== undefined) {
        addAttributes(inputEl, attributes);
    }
    appendToElement.appendChild(inputEl);
}

function createHiddenInput(id, name, value, appendToElement) {
    var attributes = new Array();
    attributes.push(new Attribute("type", "hidden"));
    attributes.push(new Attribute("value", value));
    createInput(id, name, appendToElement, attributes);
}

function createSelect(selectId, selectName, options, appendToElement, attributes) {
    var selectList = document.createElement("select");
    selectList.id = selectId;
    selectList.name = selectName;
    for (var i = 0; i < options.length; i++) {
        var option = document.createElement("option");
        option.value = options[i].value;
        option.text = options[i].text;
        if (options[i].selected) {
            option.setAttribute('selected', 'selected');
        }
        selectList.appendChild(option);
    }
    addAttributes(selectList, attributes);
    appendToElement.appendChild(selectList);
}

/**
 * adapted from createSelect. works, but needs work
 * @param listId
 * @param inputId
 * @param inputName
 * @param options
 * @param appendToElement
 * @param inputAttributes
 */
function createDatalistInput(listId, inputId, inputName, options, appendToElement, inputAttributes) {
    var dataListEl = document.createElement("datalist");
    dataListEl.id = listId;
    for (var i = 0; i < options.length; i++) {
        var option = document.createElement("option");
        option.value = options[i].value;
        /*
        if (options[i].selected) {
            option.setAttribute('selected', 'selected');
        }
        */
        dataListEl.appendChild(option);
    }
    appendToElement.appendChild(dataListEl);
    var inputEl = document.createElement("input");
    inputEl.id = inputId;
    inputEl.name = inputName;
    inputEl.setAttribute("type", "text");
    addAttributes(inputEl, inputAttributes);
    appendToElement.appendChild(inputEl);
}

// the following 3 fns from http://www.quirksmode.org/js/xmlhttp.html
function sendRequest(url, callback, postData) {
    var req = createXMLHTTPObject();
    if (!req) return;
    var method = (postData) ? "POST" : "GET";
    req.open(method,url,true);
    if (postData)
        req.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    req.onreadystatechange = function () {
        if (req.readyState != 4) return;
        if (req.status != 200 && req.status != 304) {
//          alert('HTTP error ' + req.status);
            return;
        }
        callback(req);
    }
    if (req.readyState == 4) return;
    req.send(postData);
}

var XMLHttpFactories = [
    function () {return new XMLHttpRequest()},
    function () {return new ActiveXObject("Msxml2.XMLHTTP")},
    function () {return new ActiveXObject("Msxml3.XMLHTTP")},
    function () {return new ActiveXObject("Microsoft.XMLHTTP")}
];

function createXMLHTTPObject() {
    var xmlhttp = false;
    for (var i=0;i<XMLHttpFactories.length;i++) {
        try {
            xmlhttp = XMLHttpFactories[i]();
        }
        catch (e) {
            continue;
        }
        break;
    }
    return xmlhttp;
}