import React from 'react';

const ToolCard = ({ category, color, icon, tools, title }) => {
    return (
        <div className={`bg-${color}-50 rounded-2xl p-8 text-center hover:shadow-lg transition-all duration-300 hover:scale-105 transform group`}>
            <div className={`w-20 h-20 bg-${color}-100 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300`}>
                {icon}
            </div>
            <h3 className="text-xl font-bold text-gray-900 mb-4">{title}</h3>
            <div className="space-y-3">
                {tools.map((tool, index) => (
                    <a 
                        key={index}
                        href="/register" 
                        className={`w-full bg-white hover:bg-${color}-50 text-left px-4 py-3 rounded-lg border hover:border-${color}-200 transition-all duration-200 block hover:scale-102 transform hover:shadow-md`}
                    >
                        <span className="font-medium text-gray-900">{tool.name}</span>
                        <p className="text-sm text-gray-600">{tool.description}</p>
                    </a>
                ))}
            </div>
        </div>
    );
};

export default ToolCard;
