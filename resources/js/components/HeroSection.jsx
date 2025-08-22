import React from 'react';

const HeroSection = () => {
    const scrollToTools = () => {
        const toolsSection = document.getElementById('tools-section');
        if (toolsSection) {
            toolsSection.scrollIntoView({ behavior: 'smooth' });
        }
    };

    return (
        <section className="bg-gradient-to-br from-blue-50 via-white to-purple-50 py-20 flex-grow">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center mb-16">
                    <h1 className="text-5xl md:text-6xl font-bold text-gray-900 mb-6 animate-fade-in">
                        Convert files quickly
                        <span className="text-blue-600"> and securely</span>
                    </h1>
                    <p className="text-xl text-gray-600 mb-12 max-w-3xl mx-auto animate-slide-up">
                        All-in-one file converter for documents, images, audio, and video. 
                        Fast, secure, and reliable conversion with professional quality results.
                    </p>
                    <div className="flex flex-col sm:flex-row gap-4 justify-center items-center animate-slide-up">
                        <a 
                            href="/register" 
                            className="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-8 py-4 rounded-full text-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-300 hover:scale-105 transform hover:shadow-xl animate-bounce-subtle"
                        >
                            Start Converting
                        </a>
                        <button 
                            onClick={scrollToTools}
                            className="text-blue-600 hover:text-blue-700 px-8 py-4 rounded-full text-lg font-semibold border-2 border-blue-600 hover:bg-blue-50 transition-all duration-300 hover:scale-105 transform hover:shadow-lg"
                        >
                            Browse Tools
                        </button>
                    </div>
                </div>

                {/* Feature highlights */}
                <div className="grid md:grid-cols-3 gap-8 mt-16">
                    <div className="text-center p-6 bg-white/50 backdrop-blur-sm rounded-xl hover:bg-white/70 transition-all duration-300 hover:scale-105 transform">
                        <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Lightning Fast</h3>
                        <p className="text-gray-600">Convert files in seconds with our optimized processing engine</p>
                    </div>

                    <div className="text-center p-6 bg-white/50 backdrop-blur-sm rounded-xl hover:bg-white/70 transition-all duration-300 hover:scale-105 transform">
                        <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">100% Secure</h3>
                        <p className="text-gray-600">Your files are encrypted and automatically deleted after conversion</p>
                    </div>

                    <div className="text-center p-6 bg-white/50 backdrop-blur-sm rounded-xl hover:bg-white/70 transition-all duration-300 hover:scale-105 transform">
                        <div className="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Easy to Use</h3>
                        <p className="text-gray-600">Simple drag-and-drop interface with no technical knowledge required</p>
                    </div>
                </div>
            </div>
        </section>
    );
};

export default HeroSection;
